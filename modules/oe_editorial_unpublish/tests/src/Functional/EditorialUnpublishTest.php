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
    'entity_version',
    'oe_editorial_entity_version',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
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
   * Tests the unpublishing form when the last revision is published.
   */
  public function testUnpublishFormLatestRevision(): void {
    // Publish the node so we can access the form.
    $this->node->moderation_state->value = 'published';
    $this->node->save();

    $unpublish_url = Url::fromRoute('entity.node.unpublish', [
      'node' => $this->node->id(),
    ]);

    $this->drupalGet($unpublish_url);
    // Assert we are on the correct page.
    $this->assertSession()->pageTextContains('Are you sure you want to unpublish ' . $this->node->label() . '?');
    // A cancel link is present.
    $this->assertSession()->linkExists('Cancel');
    // Assert the state select exists.
    $unpublish_state = $this->assertSession()->selectExists('Select the unpublishing state')->getValue();
    // Assert the unpublish button is there and using it we unpublish the node.
    $this->assertSession()->buttonExists('Unpublish')->press();
    $this->assertSession()->pageTextContains('The content My node has been unpublished.');
    $node = $this->nodeStorage->load($this->node->id());
    $this->assertEquals($node->moderation_state->value, $unpublish_state);
  }

  /**
   * Tests the unpublishing form when the last revision is not published.
   */
  public function testUnpublishFormWithRevisionUpdate(): void {
    // Publish the node so we can access the form. But do so in a way to also
    // increase the content version.
    $this->node->moderation_state->value = 'request_validation';
    $this->node->save();
    $this->node->moderation_state->value = 'validated';
    $this->node->save();
    $this->node->moderation_state->value = 'published';
    $this->node->save();
    $this->assertEquals(1, $this->node->get('version')->major);
    $this->assertEquals(0, $this->node->get('version')->minor);
    $this->assertEquals(0, $this->node->get('version')->patch);

    // Create a new draft of the node.
    $this->node->title = 'My node update';
    $this->node->moderation_state->value = 'draft';
    $this->node->save();

    // The minor should have increased with the new draft.
    $this->assertEquals(1, $this->node->get('version')->major);
    $this->assertEquals(1, $this->node->get('version')->minor);
    $this->assertEquals(0, $this->node->get('version')->patch);

    $this->assertCount(5, $this->nodeStorage->revisionIds($this->node));

    $unpublish_url = Url::fromRoute('entity.node.unpublish', [
      'node' => $this->node->id(),
    ]);
    $this->drupalGet($unpublish_url);
    // Assert we are on the correct page.
    $this->assertSession()->pageTextContains('Are you sure you want to unpublish My node?');
    // A cancel link is present.
    $this->assertSession()->linkExists('Cancel');
    // Assert the state select exists.
    $unpublish_state = $this->assertSession()->selectExists('Select the unpublishing state')->getValue();
    // Assert the unpublish button is there and using it we unpublish the node.
    $this->assertSession()->buttonExists('Unpublish')->press();
    $this->assertSession()->pageTextContains('The content My node has been unpublished.');

    // An extra 2 revisions got created: one for the unpublished state and one
    // for the extra draft.
    $this->assertCount(7, $this->nodeStorage->revisionIds($this->node));

    // Since none of the revisions are now published, loading the entity will
    // return the latest revision.
    $node = $this->nodeStorage->load($this->node->id());
    $this->assertEquals(7, $node->getRevisionId());
    // Assert that the current revision is the same as the previous draft.
    $this->assertEquals('draft', $node->moderation_state->value);
    $this->assertEquals('My node update', $node->label());
    // The version should have remained the same.
    $this->assertEquals(1, $node->get('version')->major);
    $this->assertEquals(1, $node->get('version')->minor);
    $this->assertEquals(0, $node->get('version')->patch);

    // Assert that the previous revision is archived.
    $previous_revision = $this->nodeStorage->loadRevision(6);
    $this->assertEquals($unpublish_state, $previous_revision->moderation_state->value);
    $this->assertEquals('My node', $previous_revision->label());
    // The archived revision has the same version as when it was published.
    $this->assertEquals(1, $previous_revision->get('version')->major);
    $this->assertEquals(0, $previous_revision->get('version')->minor);
    $this->assertEquals(0, $previous_revision->get('version')->patch);
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
