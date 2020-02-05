<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial_unpublish\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the unpublishing form for nodes.
 */
class EditorialUnpublishTest extends BrowserTestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The current user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  protected static $modules = [
    'user',
    'node',
    'system',
    'oe_editorial',
    'oe_editorial_corporate_workflow',
    'oe_editorial_workflow_demo',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->node = $this->nodeStorage->create(
      [
        'type' => 'oe_workflow_demo',
        'title' => 'My node',
        'moderation_state' => 'draft',
      ]
    );
    $this->node->save();

    /** @var \Drupal\user\RoleInterface $role */
    $role = $entity_type_manager->getStorage('user_role')->load('oe_validator');
    $this->user = $this->drupalCreateUser($role->getPermissions());
    $this->drupalLogin($this->user);
  }

  /**
   * Tests access to the unpublishing form.
   */
  public function testUnpublishAccess(): void {
    $unpublish_url = Url::fromRoute('entity.node.unpublish', [
      'node' => $this->node->id(),
    ]);

    // Assert that we can't access the unpublish page
    // when the node is not published.
    $this->assertFalse($unpublish_url->access($this->user));

    // Publish the page.
    $this->node->moderation_state->value = 'published';
    $this->node->save();

    // A user with permissions can access the unpublish page.
    $this->assertTrue($unpublish_url->access($this->user));

    // We can access the unpublish page as long as there is a published version.
    $this->node->moderation_state->value = 'draft';
    $this->node->save();
    $this->assertTrue($unpublish_url->access($this->user));

    // A user without permissions can not access the unpublish page.
    $this->drupalLogout();
    $this->drupalGet($unpublish_url);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests the unpublishing form.
   */
  public function testUnpublishForm(): void {
    // Publish the node so we can access the form.
    $this->node->moderation_state->value = 'published';
    $this->node->save();

    $unpublish_url = Url::fromRoute('entity.node.unpublish', [
      'node' => $this->node->id(),
    ]);
    $this->drupalGet($unpublish_url);
    // Assert we are in the correct page.
    $this->assertSession()->pageTextContains('Are you sure you want to unpublish the node ' . $this->node->label() . '?');
    // A cancel link is present.
    $this->assertSession()->linkExists('Cancel');
    // Assert the state select exists.
    $unpublish_state = $this->assertSession()->selectExists('Select the state to unpublish this node')->getValue();
    // Assert the unpublish button is present and using it unpublishes the node.
    $this->assertSession()->buttonExists('Unpublish')->press();
    $this->assertSession()->pageTextContains('The node My node has been unpublished.');
    $node = $this->nodeStorage->load($this->node->id());
    $this->assertEqual($node->moderation_state->value, $unpublish_state);
  }

  /**
   * Tests modules can alter the list of unpublishable states.
   */
  public function testUnpublishStatesEvent(): void {
    // Publish the node so we can access the form.
    $this->node->moderation_state->value = 'published';
    $this->node->save();

    $unpublish_url = Url::fromRoute('entity.node.unpublish', [
      'node' => $this->node->id(),
    ]);
    $this->drupalGet($unpublish_url);

    // Assert the select only contains the default values.
    $this->assertSession()->selectExists('Select the state to unpublish this node');
    $this->assertSession()->optionExists('Select the state to unpublish this node', 'Archived');
    $this->assertSession()->optionExists('Select the state to unpublish this node', 'Expired');
    $this->assertSession()->optionNotExists('Select the state to unpublish this node', 'Test');

    // Enable the test module and assert the select contains an extra value.
    $this->container->get('module_installer')->install(['oe_editorial_unpublish_test']);
    $this->drupalGet($unpublish_url);
    $this->assertSession()->selectExists('Select the state to unpublish this node');
    $this->assertSession()->optionExists('Select the state to unpublish this node', 'Archived');
    $this->assertSession()->optionExists('Select the state to unpublish this node', 'Expired');
    $this->assertSession()->optionExists('Select the state to unpublish this node', 'Test');
  }

}
