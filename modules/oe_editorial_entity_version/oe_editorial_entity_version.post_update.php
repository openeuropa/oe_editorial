<?php

/**
 * @file
 * Editorial Entity Version post update functions.
 */

declare(strict_types = 1);

/**
 * Configures the entity version action rules for corporate workflow.
 */
function oe_editorial_entity_version_post_update_configure_workflow(): void {
  // Apply entity version number rules for the corporate workflow.
  $corporate_workflow = \Drupal::configFactory()->getEditable('workflows.workflow.oe_corporate_workflow');
  $corporate_workflow->set('third_party_settings', [
    'entity_version_workflows' => [
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
    ],
  ])->save();

  $default_values = [
    'major' => 0,
    'minor' => 1,
    'patch' => 0,
  ];
  \Drupal::service('oe_editorial_entity_version.entity_version_installer')
    ->addEntityVersionFieldToWorkflowBundles('oe_corporate_workflow', $default_values);
}

/**
 * Adds the version field to bundles associated with the corporate workflow.
 */
function oe_editorial_entity_version_post_update_add_version_field(): void {
  $default_values = [
    'major' => 0,
    'minor' => 1,
    'patch' => 0,
  ];
  \Drupal::service('oe_editorial_entity_version.entity_version_installer')
    ->addEntityVersionFieldToWorkflowBundles('oe_corporate_workflow', $default_values);
}
