<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the EditorialEntityVersionInstaller service.
 */
class EntityVersionInstallerTest extends KernelTestBase {

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
    'entity_version',
    'entity_version_workflows',
    'oe_editorial_corporate_workflow',
    'oe_editorial_entity_version',
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
      'entity_version',
      'oe_editorial_corporate_workflow',
    ]);
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('system', ['sequences', 'key_value']);

    $this->nodeType = $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'name' => 'Test node type',
      'type' => 'test_node_type',
    ]);

    $this->nodeType->save();
  }

  /**
   * Tests the entity version installation.
   */
  public function testEntityVersionInstallationService(): void {
    // Install the workflow on the node type.
    $this->container->get('oe_editorial_corporate_workflow.workflow_installer')->installWorkflow($this->nodeType->id());
    $this->assertEmpty($this->container->get('entity_type.manager')->getStorage('field_config')->load('node.test_node_type.version'));

    // Install the entity version field for the workflow content types.
    $default_value = [
      'major' => 0,
      'minor' => 1,
      'patch' => 0,
    ];
    $this->container->get('oe_editorial_entity_version.entity_version_installer')->addEntityVersionFieldToWorkflowBundles('oe_corporate_workflow', $default_value);
    /** @var \Drupal\field\Entity\FieldConfig $field_config */
    $field_config = $this->container->get('entity_type.manager')->getStorage('field_config')->load('node.test_node_type.version');
    $this->assertNotEmpty($field_config);
    $actual_default_value = $field_config->getDefaultValueLiteral();
    $this->assertEquals($default_value, reset($actual_default_value));
  }

}
