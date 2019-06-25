<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_entity_version;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Handles the installation of the entity version for editorial workflows.
 */
class EditorialEntityVersionInstaller {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The field config.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $fieldConfig;

  /**
   * The field storage config.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorageConfig;

  /**
   * EditorialEntityVersionInstaller constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->configFactory = $configFactory;
    $this->fieldConfig = $entityTypeManager->getStorage('field_config');
    $this->fieldStorageConfig = $entityTypeManager->getStorage('field_storage_config');
  }

  /**
   * Assign entity version field to the bundles of the given workflow.
   *
   * @param string $workflow_id
   *   The machine name of the workflow.
   * @param array $default_value
   *   The default value of the entity version field.
   */
  public function addEntityVersionFieldToWorkflowBundles(string $workflow_id, array $default_value = []): void {
    // Get bundles associated with the corporate workflow and assign the
    // entity version field to them.
    $workflow_name = 'workflows.workflow.' . $workflow_id;
    $corporate_workflow = $this->configFactory->getEditable($workflow_name);
    $bundles = $corporate_workflow->get('type_settings.entity_types.node');

    if (empty($bundles)) {
      return;
    }

    if (!$this->fieldStorageConfig->load('node.version')) {
      $this->fieldStorageConfig->create([
        'field_name' => 'version',
        'entity_type' => 'node',
        'type' => 'entity_version',
      ])->save();
    }

    foreach ($bundles as $bundle) {
      if ($this->fieldConfig->load('node' . $bundle . 'version')) {
        continue;
      }
      $this->fieldConfig->create([
        'entity_type' => 'node',
        'field_name' => 'version',
        'bundle' => $bundle,
        'label' => 'Version',
        'cardinality' => 1,
        'translatable' => FALSE,
        'default_value' => $default_value,
      ])->save();
    }
  }

}
