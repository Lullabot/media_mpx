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
   * Returns all the Custom Fields
   *
   * @return DiscoveredCustomField[] An array of all discovered data services, indexed by service name, object type, and namespace.
   */
  public function getCustomFields(): array
  {
    $definitions = [];

    $reader = new AnnotationReader();

    // Clear the annotation loaders of any previous annotation classes.
    AnnotationRegistry::reset();
    // Register the namespaces of classes that can be used for annotations.
    AnnotationRegistry::registerLoader('class_exists');

    // Search for classes within all PSR-0 namespace locations.
    foreach ($this->getPluginNamespaces() as $namespace => $dirs) {
      foreach ($dirs as $dir) {
        if (file_exists($dir)) {
          $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
          );
          foreach ($iterator as $fileinfo) {
            if ($fileinfo->getExtension() == 'php') {
              if ($cached = $this->fileCache->get($fileinfo->getPathName())) {
                if (isset($cached['namespace'])) {
                  // Explicitly unserialize this to create a new object instance.
                  /** @var \Lullabot\Mpx\DataService\DiscoveredCustomField $discovered */
                  $discovered = unserialize($cached['content']);
                  $definitions[$cached['service']][$cached['objectType']][$cached['namespace']] = $discovered;
                }
                continue;
              }

              $sub_path = $iterator->getSubIterator()->getSubPath();
              $sub_path = $sub_path ? str_replace(DIRECTORY_SEPARATOR, '\\', $sub_path) . '\\' : '';
              $class = $namespace . '\\' . $sub_path . $fileinfo->getBasename('.php');

              // The filename is already known, so there is no need to find the
              // file. However, StaticReflectionParser needs a finder, so use a
              // mock version.
              $finder = MockFileFinder::create($fileinfo->getPathName());
              $parser = new StaticReflectionParser($class, $finder, TRUE);

              /** @var $annotation \Lullabot\Mpx\DataService\Annotation\CustomField */
              if ($annotation = $reader->getClassAnnotation($parser->getReflectionClass(), CustomField::class)) {
                if (!is_subclass_of($class, CustomFieldInterface::class)) {
                  throw new \RuntimeException(sprintf('%s must implement %s.', $class, CustomFieldInterface::class));
                }

                $discovered = new DiscoveredCustomField(
                  $class, $annotation
                );
                $definitions[$annotation->service][$annotation->objectType][$annotation->namespace] = $discovered;

                // Explicitly serialize this to create a new object instance.
                $this->fileCache->set($fileinfo->getPathName(), ['service' => $annotation->service, 'objectType' => $annotation->objectType, 'namespace' => $annotation->namespace, 'content' => serialize($discovered)]);
              }
              else {
                // Store a NULL object, so the file is not reparsed again.
                $this->fileCache->set($fileinfo->getPathName(), [NULL]);
              }
            }
          }
        }
      }
    }

    // Don't let annotation loaders pile up.
    AnnotationRegistry::reset();

    return $definitions;
  }

  /**
   * Return an array of possible plugin namespaces.
   *
   * @return array
   */
  protected function getPluginNamespaces() {
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
}
