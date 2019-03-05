<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_editorial_corporate_workflow\CorporateWorkflowInstaller;

/**
 * Tests the CorporateWorkflowInstaller service.
 */
class WorkflowInstallerTest extends KernelTestBase {

  /**
   * The node type.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $nodeType;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'field',
    'text',
    'system',
    'workflows',
    'content_moderation',
    'oe_editorial',
    'oe_editorial_corporate_workflow',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig([
      'user',
      'node',
      'system',
      'field',
      'workflows',
      'content_moderation',
      'oe_editorial_corporate_workflow',
    ]);
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');

    $this->nodeType = $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'name' => 'Test node type',
      'type' => 'test_node_type',
    ]);

    $this->nodeType->save();
  }

  /**
   * Tests the workflow installation and uninstallation.
   */
  public function testWorkflowInstallationService() {
    $workflow = $this->container->get('config.factory')->getEditable('workflows.workflow.oe_corporate_workflow');
    $this->assertEmpty($workflow->get('type_settings.entity_types'));
    $this->assertRolePermissions(FALSE);

    // Install the workflow on the node type.
    $this->container->get('oe_editorial_corporate_workflow.workflow_installer')->installWorkflow($this->nodeType->id());

    $workflow = $this->container->get('config.factory')->getEditable('workflows.workflow.oe_corporate_workflow');
    $this->assertEquals([
      'test_node_type',
    ], $workflow->get('type_settings.entity_types.node'));

    $this->assertRolePermissions(TRUE);

    // Uninstall the workflow on the node type.
    $this->container->get('oe_editorial_corporate_workflow.workflow_installer')->uninstallWorkflow($this->nodeType->id());
    $this->assertEmpty($workflow->get('type_settings.entity_types'));
    $this->assertRolePermissions(FALSE);
  }

  /**
   * Asserts that the permissions are correct.
   *
   * @param bool $positive
   *   Whether the permissions should exist on the roles or they should not.
   */
  protected function assertRolePermissions(bool $positive): void {
    $node_type = $this->nodeType->id();

    $roles = CorporateWorkflowInstaller::getRolePermissionMapping($node_type);

    foreach ($roles as $role => $permissions) {
      /** @var \Drupal\user\RoleInterface $drupal_role */
      $drupal_role = $this->container->get('entity_type.manager')->getStorage('user_role')->load($role);
      $role_permissions = $drupal_role->getPermissions();
      $expected_count = $positive ? count($permissions) : 0;
      if (count(array_intersect($permissions, $role_permissions)) !== $expected_count) {
        $this->fail(sprintf('The permissions of the %s role are not correct.', $role));
      }
    }
  }

}
