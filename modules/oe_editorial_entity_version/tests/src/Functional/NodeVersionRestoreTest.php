<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_editorial_entity_version\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_editorial_corporate_workflow\Traits\CorporateWorkflowTrait;

/**
 * Tests the node version restore.
 */
class NodeVersionRestoreTest extends BrowserTestBase {

  use CorporateWorkflowTrait;

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
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Rebuild the container as we installed the oe_editorial_workflow_demo
    // module which enables the demo content type on the corporate workflow.
    $kernel = \Drupal::service('kernel');
    $kernel->rebuildContainer();

    $entity_type_manager = $this->container->get('entity_type.manager');

    /** @var \Drupal\user\RoleInterface $role */
    $role = $entity_type_manager->getStorage('user_role')->load('oe_author');
    $permissions = $role->getPermissions();
    $role = $entity_type_manager->getStorage('user_role')->load('oe_validator');
    $permissions = array_merge($permissions, $role->getPermissions());
    $permissions[] = 'revert oe_workflow_demo revisions';
    $this->user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->user);

    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->node = $this->nodeStorage->create(
      [
        'type' => 'oe_workflow_demo',
        'title' => 'My node',
        'moderation_state' => 'draft',
        'uid' => $this->user->id(),
      ]
    );
    $this->node->save();
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
    $this->node = $this->moderateNode($this->node, 'published');
    $published_vid = $this->node->getRevisionId();

    // Revert the node to the initial revision.
    $revert_url = Url::fromRoute('node.revision_revert_confirm', [
      'node' => $this->node->id(),
      'node_revision' => $initial_revision_id,
    ]);

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
    $this->assertEquals($published_vid, $node->getRevisionId());
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
