<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_corporate_workflow;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\RoleInterface;

/**
 * Handles the installation of the corporate workflow on a content type.
 */
class CorporateWorkflowInstaller {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * CorporateWorkflowInstaller constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Installs the corporate workflow on a content type.
   *
   * @param string $content_type
   *   The content type to enable the workflow on.
   */
  public function installWorkflow(string $content_type): void {
    $config = $this->configFactory->getEditable('workflows.workflow.oe_corporate_workflow');
    $config_value = $config->get('type_settings.entity_types.node');
    $config_value[] = $content_type;
    $config->set('type_settings.entity_types.node', $config_value)->save();
    $this->handlePermissions('grant', $content_type);
  }

  /**
   * Uninstalls the corporate workflow from a content type.
   *
   * @param string $content_type
   *   The content type to uninstall the workflow from.
   */
  public function uninstallWorkflow(string $content_type): void {
    $config = $this->configFactory->getEditable('workflows.workflow.oe_corporate_workflow');
    $config_values = $config->get('type_settings.entity_types.node');
    if (!$config_values) {
      $this->handlePermissions('revoke', $content_type);
      return;
    }

    $search = array_search($content_type, $config_values);
    if ($search === FALSE) {
      $this->handlePermissions('revoke', $content_type);
      return;
    }

    unset($config_values[$search]);

    if (empty($config_values)) {
      // We save without any entity types on the workflow.
      $config->set('type_settings.entity_types', [])->save();
      $this->handlePermissions('revoke', $content_type);
      return;
    }

    $config->set('type_settings.entity_types.node', $config_values)->save();
    $this->handlePermissions('revoke', $content_type);
  }

  /**
   * Returns a mapping of the relevant workflow roles with their permissions.
   *
   * @param string $content_type
   *   The content type to include in the permissions.
   *
   * @return array
   *   The mapping.
   */
  public static function getRolePermissionMapping(string $content_type): array {
    return [
      'oe_author' => [
        "create $content_type content",
        "edit any $content_type content",
        "edit own $content_type content",
      ],
      'oe_reviewer' => [
        "delete $content_type revisions",
        "revert $content_type revisions",
      ],
      'oe_validator' => [
        "delete $content_type revisions",
        "revert $content_type revisions",
      ],
    ];
  }

  /**
   * Grants or revokes permissions to the workflow roles.
   *
   * @param string $action
   *   Grant or revoke permission.
   * @param string $content_type
   *   The content type.
   */
  protected function handlePermissions(string $action, string $content_type): void {
    if (!in_array($action, ['grant', 'revoke'])) {
      throw new \Exception('Invalid action specified');
    }

    $roles_permissions = static::getRolePermissionMapping($content_type);

    foreach ($roles_permissions as $role => $permissions) {
      $drupal_role = $this->entityTypeManager->getStorage('user_role')->load($role);
      if (!$drupal_role instanceof RoleInterface) {
        continue;
      }
      foreach ($permissions as $permission) {
        if ($action == 'revoke') {
          $drupal_role->revokePermission($permission);
          continue;
        }

        $drupal_role->grantPermission($permission);
      }

      $drupal_role->save();
    }
  }

}
