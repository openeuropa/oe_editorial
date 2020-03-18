<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial_unpublish\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_editorial_unpublish\Form\ContentEntityUnpublishForm;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;

/**
 * Tests the editorial unpublish capabilities.
 */
class EditorialUnpublishTest extends KernelTestBase {

  use UserCreationTrait;

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

    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');

    $this->installConfig([
      'user',
      'node',
      'system',
      'field',
      'workflows',
      'content_moderation',
      'oe_editorial_corporate_workflow',
    ]);

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
    $route_provider = $this->container->get('router.route_provider');

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

  /**
   * Tests access to the unpublishing form.
   */
  public function testUnpublishAccess(): void {
    $this->container->get('module_installer')->install([
      'content_translation',
      'oe_editorial_workflow_demo',
      'language',
    ]);

    $this->installEntitySchema('user');

    $this->installSchema('system', 'sequences');
    $this->installSchema('node', 'node_access');

    $this->installConfig([
      'content_translation',
      'language',
      'oe_editorial_workflow_demo',
    ]);

    $entity_type_manager = $this->container->get('entity_type.manager');

    $node = $entity_type_manager->getStorage('node')->create([
      'type' => 'test_node_type',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    /** @var \Drupal\user\RoleInterface $role */
    $role = $entity_type_manager->getStorage('user_role')->load('oe_validator');
    $user = $this->createUser($role->getPermissions());

    $unpublish_url = Url::fromRoute('entity.node.unpublish', [
      'node' => $node->id(),
    ]);

    // Assert that we can't access the unpublish page when the node is not
    // published.
    $this->assertFalse($unpublish_url->access($user));

    // Publish the page.
    $node->moderation_state->value = 'published';
    $node->save();

    // A user with permissions can access the unpublish page.
    $this->assertTrue($unpublish_url->access($user));

    // A user without permissions can not access the unpublish page.
    $anonymous = new AnonymousUserSession();
    $this->assertFalse($unpublish_url->access($anonymous));

    // We can't access the unpublish page if the last revision is not published.
    $node->moderation_state->value = 'draft';
    $node->save();
    $this->assertFalse($unpublish_url->access($user));

    // Assert we don't have access for non-moderated nodes.
    $entity_type_manager->getStorage('node_type')->create([
      'name' => 'Page',
      'type' => 'page',
    ])->save();
    $node_without_moderation = $entity_type_manager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'My node',
    ]);
    $node_without_moderation->save();
    $unpublish_url = Url::fromRoute('entity.node.unpublish', [
      'node' => $node_without_moderation->id(),
    ]);
    $this->assertFalse($unpublish_url->access($user));
  }

}
