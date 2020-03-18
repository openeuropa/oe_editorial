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

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'system',
    'oe_editorial',
    'oe_editorial_corporate_workflow',
    'oe_editorial_workflow_demo',
    'oe_editorial_unpublish_test',
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
    $this->assertSession()->pageTextContains('Are you sure you want to unpublish ' . $this->node->label() . '?');
    // A cancel link is present.
    $this->assertSession()->linkExists('Cancel');
    // Assert the state select exists.
    $unpublish_state = $this->assertSession()->selectExists('Select the unpublishing state')->getValue();
    // Assert the unpublish button is there and using it we unpublish the node.
    $this->assertSession()->buttonExists('Unpublish')->press();
    $this->assertSession()->pageTextContains('The content My node has been unpublished.');
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
    $this->assertSession()->selectExists('Select the unpublishing state');
    $this->assertSession()->optionExists('Select the unpublishing state', 'Archived');
    $this->assertSession()->optionExists('Select the unpublishing state', 'Expired');

    // Trigger the flag to remove one state from the list.
    $this->container->get('state')->set('oe_editorial_unpublish_test_remove_state', TRUE);
    $this->drupalGet($unpublish_url);
    $this->assertSession()->selectExists('Select the unpublishing state');
    $this->assertSession()->optionNotExists('Select the unpublishing state', 'Archived');
    $this->assertSession()->optionExists('Select the unpublishing state', 'Expired');
  }

}
