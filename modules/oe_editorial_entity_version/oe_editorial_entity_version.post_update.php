<?php

/**
 * @file
 * Editorial Entity Version post update functions.
 */

declare(strict_types = 1);

use Drupal\workflows\WorkflowInterface;

/**
 * Configures the entity version action rules for corporate workflow.
 */
function oe_editorial_entity_version_post_update_configure_workflow(): void {
  // Apply entity version number rules for the corporate workflow.
  $corporate_workflow = \Drupal::entityTypeManager()->getStorage('workflow')->load('oe_corporate_workflow');
  if ($corporate_workflow instanceof WorkflowInterface) {
    $data = [
      'create_new_draft' => [
        'minor' => 'increase',
      ],
      'needs_review_to_draft' => [
        'minor' => 'increase',
      ],
      'request_validation_to_draft' => [
        'minor' => 'increase',
      ],
      'validated_to_draft' => [
        'minor' => 'increase',
      ],
      'published_to_draft' => [
        'minor' => 'increase',
      ],
      'archived_to_draft' => [
        'minor' => 'increase',
      ],
      'request_validation_to_validated' => [
        'major' => 'increase',
        'minor' => 'reset',
      ],
    ];

    foreach ($data as $key => $value) {
      $corporate_workflow->setThirdPartySetting('entity_version_workflows', $key, $value);
    }
    $corporate_workflow->save();
  }

  // Get the bundles the workflow is associated with.
  $bundles = $corporate_workflow->get('type_settings')['entity_types']['node'];
  if (!$bundles) {
    return;
  }
  $default_values = [
    'major' => 0,
    'minor' => 1,
    'patch' => 0,
  ];

  \Drupal::service('entity_version.entity_version_installer')->install('node', $bundles, $default_values);
}
