<?php

declare(strict_types=1);

namespace Drupal\oe_editorial_corporate_workflow;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\RoleInterface;

/**
 * Handles the installation of the corporate workflow on a content type.
 */
class CorporateWorkflowInstaller {

  /**
   * The corporate editorial workflow.
   *
   * @var \Drupal\workflows\WorkflowInterface
   */
  protected $workflow;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * CorporateWorkflowInstaller constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->workflow = $this->entityTypeManager->getStorage('workflow')->load('oe_corporate_workflow');
  }

  /**
   * Installs the corporate workflow on a content type.
   *
   * @param string $content_type
   *   The content type to enable the workflow on.
   */
  public function installWorkflow(string $content_type): void {
    $this->workflow->getTypePlugin()->addEntityTypeAndBundle('node', $content_type);
    $this->workflow->save();
    $this->handlePermissions('grant', $content_type);
  }

  /**
   * Uninstalls the corporate workflow from a content type.
   *
   * @param string $content_type
   *   The content type to uninstall the workflow from.
   */
  public function uninstallWorkflow(string $content_type): void {
    if ($this->workflow->getTypePlugin()->appliesToEntityTypeAndBundle('node', $content_type)) {
      $this->workflow->getTypePlugin()->removeEntityTypeAndBundle('node', $content_type);
      $this->workflow->save();
    }
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
