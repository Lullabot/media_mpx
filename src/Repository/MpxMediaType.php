<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Repository;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\Entity\MediaType;
use Drupal\media_mpx\Exception\MediaTypeDoesNotExistException;
use Drupal\media_mpx\Exception\MediaTypeNotAssociatedWithMpxException;
use Drupal\media_mpx\Plugin\media\Source\Media;

/**
 * Class MpxMediaType.
 *
 * @package Drupal\media_mpx\Repository
 */
class MpxMediaType {

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * MpxMediaType constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Loads all the Media Entity Types that are managed by media_mpx.
   *
   * @return \Drupal\media\Entity\MediaType[]
   *   An array with the different mpx media types.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function findAllTypes(): array {
    $bundle_type = $this->entityTypeManager->getDefinition('media')->getBundleEntityType();
    $media_types = $this->entityTypeManager->getStorage($bundle_type)->loadMultiple();

    $mpx_types = [];
    foreach ($media_types as $type) {
      /** @var \Drupal\media\Entity\MediaType $type */
      if ($type->getSource() instanceof Media) {
        $mpx_types[] = $type;
      }
    }

    return $mpx_types;
  }

  /**
   * Finds a given mpx Media type entity by its id.
   *
   * @param string $id
   *   The type (bundle) id of the mpx media type to load.
   *
   * @return \Drupal\media\Entity\MediaType
   *   The loaded Media type.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\media_mpx\Exception\MediaTypeDoesNotExistException
   * @throws \Drupal\media_mpx\Exception\MediaTypeNotAssociatedWithMpxException
   */
  public function findByTypeId(string $id): MediaType {
    $bundle_type = $this->entityTypeManager->getDefinition('media')->getBundleEntityType();

    if (!$media_type = $this->entityTypeManager->getStorage($bundle_type)->load($id)) {
      throw new MediaTypeDoesNotExistException(sprintf('The media type %s does not exist.', $id));
    }

    /** @var \Drupal\media\Entity\MediaType $media_type */
    if (!$media_type->getSource() instanceof Media) {
      throw new MediaTypeNotAssociatedWithMpxException(sprintf('The media type %s source is not associated with mpx.', $id));
    }

    return $media_type;
  }

}
