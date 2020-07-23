<?php

/**
 * @file
 * Views hooks.
 */

use Drupal\media\Entity\MediaType;

/**
 * Implements hook_views_data_alter().
 */
function media_mpx_views_data_alter(array &$data) {
  // Look for mpx media bundles that have availability date fields mapped.
  $entity_type_manager = \Drupal::entityTypeManager();
  $bundles = $entity_type_manager->getStorage('media_type')->loadMultiple();
  $bundles = array_filter($bundles, function (MediaType $bundle) {
    if ($bundle->getSource()->getPluginId() !== 'media_mpx_media') {
      return FALSE;
    }
    $field_map = $bundle->getFieldMap();
    if (isset($field_map['Media:availableDate']) && isset($field_map['Media:expirationDate'])) {
      return TRUE;
    }
    return FALSE;
  });

  // If there are no media bundles with availability date fields mapped, doesn't
  // make sense to apply our availability filters.
  if (empty($bundles)) {
    return;
  }

  foreach ($bundles as $bundle) {
    $data['media_field_data']['media_mpx_availability_' . $bundle->id()] = [
      'title' => t('mpx availability for @bundle', ['@bundle' => $bundle->label()]),
      'help' => t('Filters based on an mpx videos availability as defined for @bundle.', ['@bundle' => $bundle->label()]),
      'filter' => [
        'field' => 'media_available_date',
        'id' => 'media_mpx_availability',
        'label' => t('mpx availability for @bundle', ['@bundle' => $bundle->label()]),
        'media_type' => $bundle->id(),
      ],
    ];
  }
}
