<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial_corporate_workflow_translation_poetry\Functional;

use Drupal\Tests\oe_editorial_corporate_workflow\Traits\CorporateWorkflowTrait;
use Drupal\Tests\oe_translation_poetry\Functional\PoetryTranslationTestBase;
use Drupal\tmgmt\Entity\Job;

/**
 * Tests the Poetry integrations with the corporate workflow.
 */
class PoetryTranslationsTest extends PoetryTranslationTestBase {

  use CorporateWorkflowTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_editorial_corporate_workflow_translation',
    'oe_editorial_corporate_workflow_translation_poetry',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);
    $this->container->get('oe_editorial_corporate_workflow.workflow_installer')->installWorkflow('page');
    $default_values = [
      'major' => 0,
      'minor' => 1,
      'patch' => 0,
    ];
    $this->container->get('entity_version.entity_version_installer')->install('node', ['page'], $default_values);
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Tests that the Poetry message is altered.
   *
   * When the user can make an update request, the translation overview message
   * is overridden to specify which version of the content ongoing translations
   * would be synced with.
   */
  public function testRequestMessageAlter(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
    ]);
    $node->save();

    // This will make it version 1.
    $node = $this->moderateNode($node, 'published');

    // Create a job to mimic that the request has been made to Poetry.
    $language = 'fr';
    $job = tmgmt_job_create('en', $language, 0);
    $job->translator = 'poetry';
    $job->addItem('content', 'node', $node->id());
    $job->set('poetry_request_id', $this->defaultIdentifierInfo);
    $job->set('state', Job::STATE_ACTIVE);
    $date = new \DateTime('05/04/2019');
    $job->set('poetry_request_date', $date->format('Y-m-d\TH:i:s'));
    $job->save();

    // Accept the request.
    $status_notification = $this->fixtureGenerator->statusNotification($this->defaultIdentifierInfo, 'ONG',
      [
        [
          'code' => 'FR',
          'date' => '05/10/2019 23:59',
          'accepted_date' => '05/10/2020 23:59',
        ],
      ]);
    $this->performNotification($status_notification);

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContainsOnce('Ongoing DGT Poetry request reference: WEB/2020/3234/0/0/TRA');
    $this->assertSession()->pageTextNotContains('Incoming translations from this request will be synchronised with version');

    // Start a new draft of the node.
    $node->set('title', 'My update title');
    $node->set('moderation_state', 'draft');
    $node->save();

    // This will make it version 2.
    $this->moderateNode($node, 'published');

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));

    $this->assertSession()->pageTextContainsOnce('Ongoing DGT Poetry request reference: WEB/2020/3234/0/0/TRA');
    $this->assertSession()->pageTextContainsOnce('Incoming translations from this request will be synchronised with version 1.0.0 of this content that has the title My node.');
    $this->assertSession()->pageTextContainsOnce('The current version that can be translated is 2.0.0.');
  }

}
