<?php

namespace Drupal\media_mpx;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Reflection\StaticReflectionParser;
use Drupal\Component\Annotation\Reflection\MockFileFinder;
use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\Utility\Crypt;
use Lullabot\Mpx\DataService\Annotation\CustomField;
use Lullabot\Mpx\DataService\CustomFieldInterface;
use Lullabot\Mpx\DataService\CustomFieldDiscoveryInterface;
use Lullabot\Mpx\DataService\DiscoveredCustomField;

/**
 * Discovers custom field implementations in any enabled Drupal module.
 *
 * @see \Drupal\Component\Annotation\Plugin\Discovery\AnnotatedClassDiscovery
 */
class CustomFieldDiscovery implements CustomFieldDiscoveryInterface {

  /**
   * The possible plugin implementation namespaces.
   *
   * @var \Traversable
   */
  private $rootNamespacesIterator;

  /**
   * A cache (usually APC) for discovered annotations.
   *
   * @var \Drupal\Component\FileCache\FileCacheInterface
   */
  private $fileCache;

  /**
   * Constructs an AnnotatedClassDiscovery object.
   *
   * @param \Traversable $root_namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   */
  public function __construct(\Traversable $root_namespaces) {
    $this->rootNamespacesIterator = $root_namespaces;
    $plugin_definition_annotation_name = CustomField::class;
    $file_cache_suffix = str_replace('\\', '_', $plugin_definition_annotation_name);
    $file_cache_suffix .= ':' . Crypt::hashBase64(serialize([]));
    $this->fileCache = FileCacheFactory::get('annotation_discovery:' . $file_cache_suffix);
  }

  /**
   * Returns all the Custom Fields.
   *
   * @return array
   *   An array of all discovered data services, indexed by service name,
   *   object type, and namespace.
   */
  public function getCustomFields(): array {
    $definitions = [];

    // Clear the annotation loaders of any previous annotation classes.
    AnnotationRegistry::reset();

    // Search for classes within all PSR-0 namespace locations.
    foreach ($this->getPluginNamespaces() as $namespace => $dirs) {
      $this->getDefinitions($definitions, $namespace, $dirs);
    }

    // Don't let annotation loaders pile up.
    AnnotationRegistry::reset();

    return $definitions;
  }

  /**
   * Return an array of possible plugin namespaces.
   *
   * @return array
   *   The possible plugin namespaces, with each array keyed by it's namespace.
   */
  private function getPluginNamespaces() {
    $plugin_namespaces = [];
    $namespaceSuffix = str_replace('/', '\\', '/Plugin/media_mpx/CustomField');
    foreach ($this->rootNamespacesIterator as $namespace => $dirs) {
      // Append the namespace suffix to the base namespace, to obtain the
      // plugin namespace; for example, 'Drupal\Views' may become
      // 'Drupal\Views\Plugin\Block'.
      $namespace .= $namespaceSuffix;
      foreach ((array) $dirs as $dir) {
        // Append the directory suffix to the PSR-4 base directory, to obtain
        // the directory where plugins are found. For example,
        // DRUPAL_ROOT . '/core/modules/views/src' may become
        // DRUPAL_ROOT . '/core/modules/views/src/Plugin/Block'.
        $plugin_namespaces[$namespace][] = $dir . '/Plugin/media_mpx/CustomField';
      }
    }

    return $plugin_namespaces;
  }

  /**
   * Set plugin definitions for a namespace and it's directories.
   *
   * @param array &$definitions
   *   The array of definitions to add to.
   * @param string $namespace
   *   The namespace implementations should belong to.
   * @param string[] $dirs
   *   An array of directory paths, relative to the app root.
   */
  private function getDefinitions(array &$definitions, string $namespace, array $dirs) {
    foreach ($dirs as $dir) {
      if (file_exists($dir)) {
        $this->fetchFromDirectory($definitions, $namespace, $dir);
      }
    }
  }

  /**
   * Fetch plugin definitions from a directory.
   *
   * @param array &$definitions
   *   The array of definitions to add to.
   * @param string $namespace
   *   The namespace implementations in the directory belong to.
   * @param string $dir
   *   The directory to search.
   */
  private function fetchFromDirectory(array &$definitions, string $namespace, string $dir) {
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileinfo) {
      if ($fileinfo->getExtension() == 'php') {
        $this->fetchFromFile($definitions, $namespace, $fileinfo, $iterator);
      }
    }
  }

  /**
   * Fetch the annotation from a file.
   *
   * @param array &$definitions
   *   The array of definitions to add to.
   * @param string $namespace
   *   The namespace the class belongs to.
   * @param \SplFileInfo $fileinfo
   *   The information about the current file.
   * @param \RecursiveIteratorIterator $iterator
   *   The iterator traversing the directory.
   */
  private function fetchFromFile(array &$definitions, string $namespace, \SplFileInfo $fileinfo, \RecursiveIteratorIterator $iterator) {
    $reader = new AnnotationReader();

    if ($this->cacheGet($definitions, $fileinfo)) {
      return;
    }

    $class = $this->parseClassName($namespace, $fileinfo, $iterator);

    // The filename is already known, so there is no need to find the
    // file. However, StaticReflectionParser needs a finder, so use a
    // mock version.
    $finder = MockFileFinder::create($fileinfo->getPathName());
    $parser = new StaticReflectionParser($class, $finder, TRUE);

    /** @var \Lullabot\Mpx\DataService\Annotation\CustomField $annotation */
    if (!$annotation = $reader->getClassAnnotation($parser->getReflectionClass(), CustomField::class)) {
      // Store a NULL object, so the file is not reparsed again.
      $this->fileCache->set($fileinfo->getPathName(), [NULL]);
      return;
    }

    if (!is_subclass_of($class, CustomFieldInterface::class)) {
      throw new \RuntimeException(sprintf('%s must implement %s.', $class, CustomFieldInterface::class));
    }

    $discovered = new DiscoveredCustomField(
      $class, $annotation
    );
    $definitions[$annotation->service][$annotation->objectType][$annotation->namespace] = $discovered;
    $this->cacheSet($fileinfo, $discovered);
  }

  /**
   * Get the definitions from the cache, if possible.
   *
   * @param array &$definitions
   *   The array of definitions to add to.
   * @param \SplFileInfo $fileinfo
   *   The information about the current file.
   *
   * @return bool
   *   TRUE if the cache was hit and $definitions has been populated, FALSE
   *   otherwise.
   */
  private function cacheGet(array &$definitions, \SplFileInfo $fileinfo): bool {
    if ($cached = $this->fileCache->get($fileinfo->getPathName())) {
      if (isset($cached['namespace'])) {
        // Explicitly unserialize this to create a new object instance.
        /** @var \Lullabot\Mpx\DataService\DiscoveredCustomField $discovered */
        $discovered = unserialize($cached['content']);
        $definitions[$cached['service']][$cached['objectType']][$cached['namespace']] = $discovered;

        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Parse the class name from a file.
   *
   * @param string $namespace
   *   The namespace the class belongs to.
   * @param \SplFileInfo $fileinfo
   *   The information about the current file.
   * @param \RecursiveIteratorIterator $iterator
   *   The iterator traversing the directory.
   *
   * @return string
   *   The fully-qualified class name.
   */
  private function parseClassName(string $namespace, \SplFileInfo $fileinfo, \RecursiveIteratorIterator $iterator): string {
    $sub_path = $iterator->getSubIterator()->getSubPath();
    $sub_path = $sub_path ? str_replace(DIRECTORY_SEPARATOR, '\\', $sub_path) . '\\' : '';
    $class = $namespace . '\\' . $sub_path . $fileinfo->getBasename('.php');
    return $class;
  }

  /**
   * Set a discovered custom field class data into the cache.
   *
   * @param \SplFileInfo $fileinfo
   *   The information about the current file.
   * @param \Lullabot\Mpx\DataService\DiscoveredCustomField $discovered
   *   The discovered Custom Field class.
   */
  private function cacheSet(\SplFileInfo $fileinfo, DiscoveredCustomField $discovered) {
    $annotation = $discovered->getAnnotation();
    // Explicitly serialize this to create a new object instance.
    $this->fileCache->set($fileinfo->getPathName(), [
      'service' => $annotation->service,
      'objectType' => $annotation->objectType,
      'namespace' => $annotation->namespace,
      'content' => serialize($discovered),
    ]);
  }

}
