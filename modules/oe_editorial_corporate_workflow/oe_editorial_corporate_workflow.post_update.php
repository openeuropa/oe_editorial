<?php

/**
 * @file
 * OpenEuropa Editorial Corporate Workflow post updates.
 */

declare(strict_types = 1);

use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Add missing permission for Validator.
 */
function oe_editorial_corporate_workflow_post_update_set_validator_permission(array &$sandbox): void {
  $role = Role::load('oe_validator');
  $role
    ->grantPermission('use oe_corporate_workflow transition validated_to_draft')
    ->save();
}

/**
 * Adapts translator role name.
 */
function oe_editorial_corporate_workflow_post_update_00001(): void {
  $role = \Drupal::entityTypeManager()
    ->getStorage('user_role')
    ->load('oe_translator');

  if (!$role instanceof RoleInterface) {
    return;
  }

  $role->set('label', 'Translate content')->save();
}
