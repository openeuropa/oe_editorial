<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial_unpublish\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_editorial_unpublish\Form\ContentEntityUnpublishForm;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;

/**
 * Tests the editorial unpublish capabilities.
 */
class EditorialUnpublishTest extends KernelTestBase {

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
    'oe_editorial_unpublish',
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

    $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'name' => 'Test node type',
      'type' => 'test_node_type',
    ])->save();

    $this->container->get('oe_editorial_corporate_workflow.workflow_installer')->installWorkflow('test_node_type');
  }

  /**
   * Tests that the Unpublish capability only applies to moderated content.
   */
  public function testAppliesCorrectly(): void {
    $route_provider = \Drupal::service('router.route_provider');

    foreach ($this->container->get('entity_type.manager')->getDefinitions() as $definition) {
      if ($definition->id() === 'node') {
        $this->assertEquals(ContentEntityUnpublishForm::class, $definition->getFormClass('unpublish'));
        $this->assertInstanceOf(Route::class, $route_provider->getRouteByName('entity.node.unpublish'));
        continue;
      }

      $this->assertNull($definition->getFormClass('unpublish'));
      try {
        $route_provider->getRouteByName('entity.' . $definition->id() . '.unpublish');
      }
      catch (\Exception $exception) {
        $this->assertInstanceOf(RouteNotFoundException::class, $exception);
      }
    }
  }

}
