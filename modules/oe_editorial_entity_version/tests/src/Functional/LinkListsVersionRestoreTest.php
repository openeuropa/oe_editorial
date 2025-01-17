<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_editorial_entity_version\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_editorial_corporate_workflow\Traits\CorporateWorkflowTrait;

/**
 * Tests the "link lists" version restore form and logic.
 */
class LinkListsVersionRestoreTest extends BrowserTestBase {

  use CorporateWorkflowTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The test link list.
   *
   * @var \Drupal\oe_link_lists\Entity\LinkListInterface
   */
  protected $linkList;

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
    'system',
    'entity_version',
    'oe_editorial',
    'oe_editorial_corporate_workflow',
    'oe_editorial_entity_version',
    'oe_link_lists',
    'oe_link_lists_test',
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

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $workflow = $this->entityTypeManager->getStorage('workflow')->load('oe_corporate_workflow');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('link_list', 'dynamic');
    $workflow->save();

    // Add entity version field to corporate workflow bundles.
    $bundles = $workflow->get('type_settings')['entity_types']['link_list'] ?? [];

    $default_values = [
      'major' => 0,
      'minor' => 1,
      'patch' => 0,
    ];

    \Drupal::service('entity_version.entity_version_installer')->install('link_list', $bundles, $default_values);

    // We apply the entity version setting for the version field.
    \Drupal::entityTypeManager()->getStorage('entity_version_settings')->create([
      'target_entity_type_id' => 'link_list',
      'target_bundle' => 'dynamic',
      'target_field' => 'version',
    ])->save();

    $kernel = \Drupal::service('kernel');
    $kernel->rebuildContainer();

    /** @var \Drupal\user\RoleInterface $role */
    $role = $this->entityTypeManager->getStorage('user_role')->load('oe_author');
    $permissions = $role->getPermissions();
    $role = $this->entityTypeManager->getStorage('user_role')->load('oe_validator');
    $permissions = array_merge($permissions, $role->getPermissions());
    $permissions[] = 'create dynamic link list';
    $permissions[] = 'edit dynamic link list';
    $permissions[] = 'revert any dynamic link list revisions';
    $this->user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->user);

    $this->linkList = $this->entityTypeManager->getStorage('link_list')->create([
      'bundle' => 'dynamic',
      'title' => 'My link list',
      'administrative_title' => $this->randomMachineName(),
    ]);
    $configuration = [
      'source' => [
        'plugin' => 'test_empty_collection',
        'plugin_configuration' => ['url' => 'http://example.com'],
      ],
      'display' => [
        'plugin' => 'title',
        'plugin_configuration' => [],
      ],
      'no_results_behaviour' => [
        'plugin' => 'hide_list',
        'plugin_configuration' => [],
      ],
    ];
    $this->linkList->setConfiguration($configuration);
    $this->linkList->save();
  }

  /**
   * Tests the link list revision revert confirm form.
   */
  public function testLinkListRevisionRevertConfirmForm(): void {
    $initial_revision_id = $this->linkList->getRevisionId();
    $initial_version = [
      $this->linkList->version->major,
      $this->linkList->version->minor,
      $this->linkList->version->patch,
    ];
    $initial_revision_date = $this->linkList->getRevisionCreationTime();

    // Now create a new major version with a different title.
    $this->linkList->setTitle('My link list ready to publish');
    $this->linkList = $this->moderateEntity($this->linkList, 'published');
    $published_vid = $this->linkList->getRevisionId();

    // Revert the link list to the initial revision.
    $revert_url = Url::fromRoute('entity.link_list.revision_revert_form', [
      'link_list' => $this->linkList->id(),
      'link_list_revision' => $initial_revision_id,
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

    // Reload the link list.
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('link_list');
    $link_list = $storage->load($this->linkList->id());
    // The link list should be still published.
    $this->assertEquals('My link list ready to publish', $this->linkList->getTitle());
    $this->assertFalse($link_list->isLatestRevision());
    $this->assertTrue($link_list->isDefaultRevision());
    $this->assertTrue($link_list->isPublished());
    $this->assertEquals('published', $link_list->moderation_state->value);
    $this->assertEquals($published_vid, $link_list->getRevisionId());
    // Load the latest revision.
    $latest_revision = $storage->loadRevision($storage->getLatestRevisionId($link_list->id()));
    $this->assertTrue($latest_revision->isLatestRevision());
    $this->assertEquals('My link list', $latest_revision->getTitle());
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
