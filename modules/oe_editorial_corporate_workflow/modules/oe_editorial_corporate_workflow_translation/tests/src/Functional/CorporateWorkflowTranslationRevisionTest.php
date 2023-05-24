<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial_corporate_workflow_translation\Functional;

use Drupal\content_moderation\Entity\ContentModerationState;
use Drupal\content_moderation\Entity\ContentModerationState as ContentModerationStateEntity;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_editorial_corporate_workflow\Traits\CorporateWorkflowTrait;

/**
 * Tests the translation revision capability.
 *
 * Using the corporate editorial workflow, translations need to be saved onto
 * the latest revision of the entity's major version. In other words, if the
 * translation is started when the entity is in validated state (the minimum),
 * and the entity gets published before the translation comes back, the latter
 * should be saved on the published revision. But not on any future drafts
 * which create new minor versions.
 */
class CorporateWorkflowTranslationRevisionTest extends BrowserTestBase {

  use CorporateWorkflowTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
    'datetime',
    'tmgmt',
    'tmgmt_local',
    'tmgmt_content',
    'node',
    'toolbar',
    'content_translation',
    'user',
    'field',
    'text',
    'options',
    'oe_editorial_workflow_demo',
    'oe_translation',
    'oe_editorial_corporate_workflow_translation',
    'paragraphs',
    'oe_editorial_corporate_workflow_translation_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = \Drupal::service('entity_type.manager');

    $this->entityTypeManager->getStorage('node_type')->create([
      'name' => 'Page',
      'type' => 'page',
    ])->save();

    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);
    \Drupal::service('oe_editorial_corporate_workflow.workflow_installer')->installWorkflow('page');
    $default_values = [
      'major' => 0,
      'minor' => 1,
      'patch' => 0,
    ];
    \Drupal::service('entity_version.entity_version_installer')->install('node', ['page'], $default_values);
    // We apply the entity version setting for the version field.
    $this->entityTypeManager->getStorage('entity_version_settings')->create([
      'target_entity_type_id' => 'node',
      'target_bundle' => 'page',
      'target_field' => 'version',
    ])->save();
    \Drupal::service('router.builder')->rebuild();

    $form_display = EntityFormDisplay::load('node.oe_workflow_demo.default');
    $form_display->setComponent('field_workflow_paragraphs', [
      'type' => 'entity_reference_paragraphs',
      'settings' => [
        'title' => 'Paragraph',
        'title_plural' => 'Paragraphs',
        'edit_mode' => 'open',
        'add_mode' => 'dropdown',
        'form_display_mode' => 'default',
        'default_paragraph_type' => 'workflow_paragraph',
      ],
      'third_party_settings' => [],
      'region' => 'content',
    ]);
    $form_display->save();

    /** @var \Drupal\user\RoleInterface $role */
    $role = $this->entityTypeManager->getStorage('user_role')->load('oe_translator');
    $this->user = $this->drupalCreateUser($role->getPermissions());

    $this->drupalLogin($this->user);
  }

  /**
   * Tests that users can only create translations of validated content.
   */
  public function testTranslationAccess(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    // Ensure we can only create translation task if the node is validated or
    // published.
    $local_task_creation_url = Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'fr',
      'entity_type' => 'node',
    ]);
    $this->assertFalse($local_task_creation_url->access($this->user));

    $node->set('moderation_state', 'needs_review');
    $node->save();
    $this->assertFalse($local_task_creation_url->access($this->user));

    $node->set('moderation_state', 'request_validation');
    $node->save();
    $this->assertFalse($local_task_creation_url->access($this->user));

    // Navigate to the translation overview page and assert we don't have a link
    // to start a translation.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    // No link for regular Drupal core translation creation.
    $this->assertSession()->linkNotExists('Add');
    // No link for the local translation.
    $this->assertSession()->linkNotExists('Translate locally');

    $node->set('moderation_state', 'validated');
    $node->save();
    $this->assertTrue($local_task_creation_url->access($this->user));

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->linkNotExists('Add');
    $this->assertSession()->linkExists('Translate locally');

    $node->set('moderation_state', 'published');
    $node->save();
    $this->assertTrue($local_task_creation_url->access($this->user));

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->linkNotExists('Add');
    $this->assertSession()->linkExists('Translate locally');

    // If we start a new draft, then we block access to creating a new
    // translation until the content is validated again.
    $node->set('moderation_state', 'draft');
    $node->save();
    $this->assertFalse($local_task_creation_url->access($this->user));
  }

  /**
   * Tests the creation of new translations using the workflow.
   */
  public function testModeratedTranslationCreation(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $node = $this->moderateNode($node, 'validated');

    // At this point, we expect to have 4 revisions of the node.
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(4, $revision_ids);

    // Create a local translation task.
    $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'fr',
      'entity_type' => 'node',
    ]));
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node');

    // At this point the job item and local task have been created for the
    // translation and they should reference the last revision of the node, that
    // of the validated revision.
    $job_items = $this->entityTypeManager->getStorage('tmgmt_job_item')->loadByProperties(['item_rid' => $node->getRevisionId()]);
    $this->assertCount(1, $job_items);

    // Publish the node before finalizing the translation.
    $node = $this->moderateNode($node, 'published');
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(5, $revision_ids);

    // Finalize the translation and check that the translation got saved onto
    // the published version rather than the validated one where it actually
    // got started.
    $values = [
      'Translation' => 'My node FR',
    ];
    // It should be the first local task item created so we use the ID 1.
    $url = Url::fromRoute('entity.tmgmt_local_task_item.canonical', ['tmgmt_local_task_item' => 1]);

    $this->drupalGet($url);
    $this->submitForm($values, t('Save and complete translation'));
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface[] $revisions */
    $revisions = $node_storage->loadMultipleRevisions($revision_ids);
    foreach ($revisions as $revision) {
      if ($revision->get('moderation_state')->value === 'published') {
        $this->assertTrue($revision->hasTranslation('fr'), 'The published node does not have a translation');
        continue;
      }

      $this->assertFalse($revision->hasTranslation('fr'), sprintf('The %s node has a translation and it shouldn\'t', $revision->get('moderation_state')->value));
    }

    // Start a new draft from the latest published node and validate it.
    $node = $node_storage->load($node->id());
    $node->set('title', 'My node 2');
    $node->set('moderation_state', 'draft');
    $node->save();
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(6, $revision_ids);
    $node = $this->moderateNode($node, 'validated');
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(9, $revision_ids);
    // Assert that the latest revision that was just validated is the correct
    // version and inherited the translation from the previous version.
    /** @var \Drupal\node\NodeInterface $validated_node */
    $validated_node = $node_storage->loadRevision($node_storage->getLatestRevisionId($node->id()));
    $this->assertEquals('2', $validated_node->get('version')->major);
    $this->assertEquals('0', $validated_node->get('version')->minor);
    $this->assertEquals('My node FR', $validated_node->getTranslation('fr')->label());

    // Create a new local translation task.
    $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'fr',
      'entity_type' => 'node',
    ]));

    // The default translation value comes from the previous version
    // translation.
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node FR');

    // Assert that the new job item got created and it has the revision ID of
    // the validated node.
    $job_items = $this->entityTypeManager->getStorage('tmgmt_job_item')->loadByProperties(['item_rid' => $validated_node->getRevisionId()]);
    $this->assertCount(1, $job_items);

    // Publish the node before finalizing the translation.
    $this->moderateNode($node, 'published');
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(10, $revision_ids);

    // Finalize the translation and check that the translation got saved onto
    // the published version rather than the validated one where it actually
    // got started.
    $values = [
      'Translation' => 'My node FR 2',
    ];
    // It should be the second local task item created so we use the ID 2.
    $url = Url::fromRoute('entity.tmgmt_local_task_item.canonical', ['tmgmt_local_task_item' => 2]);
    $this->drupalGet($url);
    $this->submitForm($values, t('Save and complete translation'));
    $node_storage->resetCache();
    $validated_node = $node_storage->loadRevision($validated_node->getRevisionId());
    // The second validated revision should have the old FR translation.
    $this->assertEquals('My node FR', $validated_node->getTranslation('fr')->label());
    $node = $node_storage->load($node->id());
    // The new (current) published revision should have the new FR translation.
    $this->assertEquals('My node FR 2', $node->getTranslation('fr')->label());

    // The previous published revisions have the old FR translation.
    $revision_ids = $node_storage->revisionIds($node);
    /** @var \Drupal\node\NodeInterface[] $revisions */
    $revisions = $node_storage->loadMultipleRevisions($revision_ids);
    foreach ($revisions as $revision) {
      if ($revision->isPublished() && (int) $revision->get('version')->major === 1) {
        $this->assertEquals('My node FR', $revision->getTranslation('fr')->label());
        break;
      }
    }
  }

  /**
   * Tests that revision translations are carried over from latest revision.
   *
   * The test focuses on ensuring that when a new revision is created by the
   * storage based on another one, the new one inherits the translated values
   * from the one its based on and NOT from the latest default revision as core
   * would have it.
   *
   * @see oe_editorial_corporate_workflow_translation_node_revision_create()
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function testTranslationRevisionsCarryOver(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create a validated node directly and translate it.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $node = $this->moderateNode($node, 'validated');
    $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'fr',
      'entity_type' => 'node',
    ]));
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node');
    $values = [
      'Translation' => 'My node FR',
    ];
    // It should be the first local task item created so we use the ID 1.
    $url = Url::fromRoute('entity.tmgmt_local_task_item.canonical', ['tmgmt_local_task_item' => 1]);
    $this->drupalGet($url);
    $this->submitForm($values, t('Save and complete translation'));

    $node = $node_storage->load($node->id());
    // Publish the node and check that the translation is available in the
    // published revision.
    $node = $this->moderateNode($node, 'published');
    $revision_ids = $node_storage->revisionIds($node);

    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface[] $revisions */
    $revisions = $node_storage->loadMultipleRevisions($revision_ids);
    // Since we translated the node while it was validated, both revisions
    // should contain the same translation.
    foreach ($revisions as $revision) {
      if ($revision->isPublished() || $revision->get('moderation_state')->value === 'validated') {
        $this->assertTrue($revision->hasTranslation('fr'), 'The revision does not have a translation');
        $this->assertEquals('My node FR', $revision->getTranslation('fr')->label(), 'The revision does not have a correct translation');
      }
    }

    // Start a new draft from the latest published node and validate it.
    $node->set('title', 'My node 2');
    $node->set('moderation_state', 'draft');
    $node->save();

    $node = $this->moderateNode($node, 'validated');
    $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'fr',
      'entity_type' => 'node',
    ]));

    // The default translation value comes from the previous version
    // translation.
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node FR');
    $values = [
      'Translation' => 'My node FR 2',
    ];
    // It should be the second local task item created so we use the ID 2.
    $url = Url::fromRoute('entity.tmgmt_local_task_item.canonical', ['tmgmt_local_task_item' => 2]);
    $this->drupalGet($url);
    $this->submitForm($values, t('Save and complete translation'));

    // Publish the node and check that the published versions have the correct
    // translations. Since we have previously published revisions, we need to
    // use the latest revision to transition to the published state.
    $revision_id = $node_storage->getLatestRevisionId($node->id());
    $node = $node_storage->loadRevision($revision_id);
    $node = $this->moderateNode($node, 'published');
    $revision_ids = $node_storage->revisionIds($node);

    /** @var \Drupal\node\NodeInterface[] $revisions */
    $revisions = $node_storage->loadMultipleRevisions($revision_ids);
    foreach ($revisions as $revision) {
      if ($revision->isPublished() && (int) $revision->get('version')->major === 1) {
        $this->assertEquals('My node FR', $revision->getTranslation('fr')->label());
        continue;
      }

      if ($revision->isPublished() && (int) $revision->get('version')->major === 2) {
        $this->assertEquals('My node FR 2', $revision->getTranslation('fr')->label());
        continue;
      }
    }

    // Test that if the default revision of the content
    // has less translations than the revision where we make a new revision
    // from, the new revision will include all the translations from the
    // previous revision not only the ones from the default revision.
    // Start a new draft from the latest published node and validate it.
    $node = $node_storage->load($node->id());
    $node->set('title', 'My node 3');
    $node->set('moderation_state', 'draft');
    $node->save();

    $node = $this->moderateNode($node, 'validated');
    // Create some more translations.
    $task_id = 3;
    foreach (['fr', 'it', 'ro'] as $langcode) {
      $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
        'entity' => $node->id(),
        'source' => 'en',
        'target' => $langcode,
        'entity_type' => 'node',
      ]));

      $values = [
        'Translation' => "My node $langcode 3",
      ];
      $url = Url::fromRoute('entity.tmgmt_local_task_item.canonical', ['tmgmt_local_task_item' => $task_id]);
      $task_id++;
      $this->drupalGet($url);
      $this->submitForm($values, t('Save and complete translation'));
    }

    // Publish the content and assert that the new published version has
    // translations in 4 languages.
    $revision_id = $node_storage->getLatestRevisionId($node->id());
    $node = $node_storage->loadRevision($revision_id);
    $node = $this->moderateNode($node, 'published');
    foreach (['fr', 'it', 'ro'] as $langcode) {
      $this->assertTrue($node->hasTranslation($langcode), 'Translation missing in ' . $langcode);
    }

    // Test that translations carry over works also with embedded entities.
    // These are entities such as paragraphs which are considered as composite,
    // depend on the parent via the entity_reference_revisions
    // entity_revision_parent_id_field.
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $paragraph = Paragraph::create([
      'type' => 'workflow_paragraph',
      'field_workflow_paragraph_text' => 'the paragraph text value',
    ]);
    $paragraph->save();

    $node = Node::create([
      'type' => 'oe_workflow_demo',
      'title' => 'Node with a paragraph',
      'field_workflow_paragraphs' => [
        [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ],
      ],
    ]);

    $node->save();
    // Publish the node.
    $node = $this->moderateNode($node, 'published');

    // Add a translation to the published version.
    $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'fr',
      'entity_type' => 'node',
    ]));
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'Node with a paragraph');
    $this->assertSession()->elementContains('css', '#edit-field-workflow-paragraphs0entityfield-workflow-paragraph-text0value-translation', 'the paragraph text value');
    $values = [
      'title|0|value[translation]' => 'Node with a paragraph FR',
      'field_workflow_paragraphs|0|entity|field_workflow_paragraph_text|0|value[translation]' => 'the paragraph text value FR',
    ];

    $ids = \Drupal::entityTypeManager()->getStorage('tmgmt_local_task_item')->getQuery()
      ->accessCheck(FALSE)
      ->sort('tltiid', 'DESC')
      ->execute();
    $id = reset($ids);
    $url = Url::fromRoute('entity.tmgmt_local_task_item.canonical', ['tmgmt_local_task_item' => $id]);
    $this->drupalGet($url);
    $this->submitForm($values, t('Save and complete translation'));

    // Make a new draft and change the node and paragraph.
    $user = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($user);
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalGet($node->toUrl());
    $this->clickLink('New draft');
    $this->getSession()->getPage()->fillField('title[0][value]', 'Node with a paragraph - updated');
    $this->getSession()->getPage()->fillField('field_workflow_paragraphs[0][subform][field_workflow_paragraph_text][0][value]', 'the paragraph text value - updated');
    $this->getSession()->getPage()->pressButton('Save (this translation)');
    $this->assertSession()->pageTextContains('Node with a paragraph - updated has been updated.');
    // Validate the node.
    $this->getSession()->getPage()->selectFieldOption('Change to', 'Validated');
    // Submitting the form will trigger a batch, so use the correct method to
    // account for redirects.
    $this->submitForm([], 'Apply');
    $this->assertSession()->pageTextContains('The moderation state has been updated.');
    // Update also the translation.
    $this->clickLink('Translate');
    $this->getSession()->getPage()->find('css', '.tmgmttranslate-localadd a[hreflang="fr"]')->click();
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'Node with a paragraph FR');
    $this->assertSession()->elementContains('css', '#edit-field-workflow-paragraphs0entityfield-workflow-paragraph-text0value-translation', 'the paragraph text value FR');
    $values = [
      'title|0|value[translation]' => 'Node with a paragraph FR 2',
      'field_workflow_paragraphs|0|entity|field_workflow_paragraph_text|0|value[translation]' => 'the paragraph text value FR 2',
    ];
    $this->submitForm($values, t('Save and complete translation'));
    // Go back to the node and publish it.
    $this->drupalGet($node->toUrl());
    $this->clickLink('View draft');
    $this->getSession()->getPage()->selectFieldOption('Change to', 'Published');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->assertSession()->pageTextContains('The moderation state has been updated.');
    $node_storage->resetCache();
    $revision_id = $node_storage->getLatestRevisionId($node->id());
    $revision = $node_storage->loadRevision($revision_id);
    $node_translation = $revision->getTranslation('fr');
    $this->assertEquals('Node with a paragraph - updated', $revision->label());
    $this->assertEquals('Node with a paragraph FR 2', $node_translation->label());
    $paragraph = $revision->get('field_workflow_paragraphs')->entity;
    $paragraph_translation = $paragraph->getTranslation('fr');
    $this->assertEquals('the paragraph text value - updated', $paragraph->get('field_workflow_paragraph_text')->value);
    $this->assertEquals('the paragraph text value FR 2', $paragraph_translation->get('field_workflow_paragraph_text')->value);
  }

  /**
   * Tests that moderation state translations are kept in sync with original.
   */
  public function testModerationStateSync(): void {
    // Create a validated node and add a translation.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $node = $this->moderateNode($node, 'validated');
    $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'fr',
      'entity_type' => 'node',
    ]));
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node');
    $values = [
      'Translation' => 'My node FR',
    ];
    // It should be the first local task item created so we use the ID 1.
    $url = Url::fromRoute('entity.tmgmt_local_task_item.canonical', ['tmgmt_local_task_item' => 1]);
    $this->drupalGet($url);
    $this->submitForm($values, t('Save and complete translation'));

    // Assert that the node has two translations and the moderation state entity
    // also has two translations.
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertCount(2, $node->getTranslationLanguages());
    $moderation_state = ContentModerationState::loadFromModeratedEntity($node);
    $this->assertCount(2, $moderation_state->getTranslationLanguages());

    // Assert that both the node and moderation state translations are
    // "validated".
    $assert_translation_state = function (ContentEntityInterface $entity, $state) {
      foreach ($entity->getTranslationLanguages() as $language) {
        $translation = $entity->getTranslation($language->getId());
        $this->assertEquals($state, $translation->get('moderation_state')->value, sprintf('The %s language has the %s state', $language->getName(), $state));
      }
    };
    $assert_translation_state($node, 'validated');
    $assert_translation_state($moderation_state, 'validated');

    // "Break" the system by deleting the moderation state entity translation.
    $moderation_state->removeTranslation('fr');
    ContentModerationStateEntity::updateOrCreateFromEntity($moderation_state);

    // Now only the original will be validated, and the translation of the node
    // becomes "draft" because it no longer is translated. This situation
    // should not really occur, but if it does, it can break new translations
    // which when being saved onto the node, cause the moderation state of
    // the original to be set to draft instead of keeping it on validated.
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertEquals('validated', $node->get('moderation_state')->value);
    $this->assertEquals('draft', $node->getTranslation('fr')->get('moderation_state')->value);

    // Create a new translation.
    $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'fr',
      'entity_type' => 'node',
    ]));
    $values = [
      'Translation' => 'My node FR 2',
    ];
    // It should be the first local task item created so we use the ID 1.
    $url = Url::fromRoute('entity.tmgmt_local_task_item.canonical', ['tmgmt_local_task_item' => 2]);
    $this->drupalGet($url);
    $this->submitForm($values, t('Save and complete translation'));

    // Assert the node is still in validated state and the content moderation
    // state entity got its translation back.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $assert_translation_state($node, 'validated');
    $moderation_state = ContentModerationState::loadFromModeratedEntity($node);
    $this->assertCount(2, $moderation_state->getTranslationLanguages());
    $assert_translation_state($moderation_state, 'validated');
  }

}
