<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial_entity_version\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the node revision revert.
 */
class NodeRevisionRevertTest extends BrowserTestBase {

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
    'entity_version',
    'oe_editorial',
    'oe_editorial_corporate_workflow',
    'oe_editorial_workflow_demo',
    'oe_editorial_entity_version',
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
    $permissions = $role->getPermissions();
    $permissions[] = 'administer nodes';
    $this->user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests the node revision revert confirm form.
   */
  public function testNodeRevisionRevertConfirmForm(): void {
    $initial_revision_id = $this->node->getRevisionId();
    $initial_version = [
      $this->node->version->major,
      $this->node->version->minor,
      $this->node->version->patch,
    ];
    $initial_revision_date = $this->node->getRevisionCreationTime();

    // Now create a new major version with a different title.
    $this->node->setTitle('My node ready to publish');
    $this->node->moderation_state->value = 'request_validation';
    $this->node->save();
    $this->node->moderation_state->value = 'validated';
    $this->node->save();
    // Now Publish the node.
    $this->node->moderation_state->value = 'published';
    $this->node->save();

    // Revert the node to the initial revision.
    $revert_url = Url::fromRoute('node.revision_revert_confirm', [
      'node' => $this->node->id(),
      'node_revision' => $initial_revision_id,
    ]);

    // Assert that the user can't access the url because lacks permission.
    $this->assertFalse($revert_url->access($this->user));

    // Grant the 'restore version' permission to the user.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $role = $entity_type_manager->getStorage('user_role')->load($this->user->getRoles(TRUE)[0]);
    $role->grantPermission('restore version');
    $role->save();

    // Assert that the user can access the url.
    $this->assertTrue($revert_url->access($this->user));

    $this->drupalGet($revert_url);

    $date_formatter = $this->container->get('date.formatter');
    // Assert we are on the correct page with the correct confirm message.
    $this->assertSession()->pageTextContains('Are you sure you want to restore the ' . implode('.', $initial_version) . ' version from ' . $date_formatter->format($initial_revision_date) . '?');
    // A cancel button is present.
    $this->assertSession()->linkExists('Cancel');
    // Assert the restore button exist and restore the version by pressing it.
    $this->assertSession()->buttonExists('Restore')->press();
    $this->assertSession()->pageTextContains('Version 0.1.0 has been restored.');

    // Reload the node.
    $node = $this->nodeStorage->load($this->node->id());
    // The node should be still published.
    $this->assertEquals('My node ready to publish', $this->node->getTitle());
    $this->assertFalse($node->isLatestRevision());
    $this->assertTrue($node->isDefaultRevision());
    $this->assertTrue($node->isPublished());
    $this->assertEquals('published', $node->moderation_state->value);
    // Load the latest revision.
    $latest_revision = $this->nodeStorage->loadRevision($this->nodeStorage->getLatestRevisionId($node->id()));
    $this->assertTrue($latest_revision->isLatestRevision());
    $this->assertEquals('My node', $latest_revision->getTitle());
    $this->assertEquals('draft', $latest_revision->moderation_state->value);
    $this->assertEquals('Version 0.1.0 has been restored by ' . $this->user->getDisplayName() . '.', $latest_revision->getRevisionLogMessage());
    $current_version = $latest_revision->version->getValue();
    $new_expected_version = [
      'major' => '1',
      'minor' => '1',
      'patch' => '0',
    ];
    $this->assertEquals($new_expected_version, reset($current_version));
  }

}
