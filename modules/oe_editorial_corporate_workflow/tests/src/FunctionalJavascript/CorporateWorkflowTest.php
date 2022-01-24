<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial_corporate_workflow\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_editorial\Traits\BatchTrait;

/**
 * Tests the corporate workflow logic.
 */
class CorporateWorkflowTest extends WebDriverTestBase {

  use BatchTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'field',
    'text',
    'options',
    'oe_editorial_workflow_demo',
    'oe_editorial_corporate_workflow',
    'oe_editorial_corporate_workflow_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $display = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'oe_workflow_demo',
      'mode' => 'default',
    ])->setStatus(TRUE);
    $display->setComponent('content_moderation_control');
    $display->save();
  }

  /**
   * Tests that the entity state transition updates the original entity state.
   */
  public function testBatchEntityStateTransition(): void {
    $node = $this->drupalCreateNode([
      'type' => 'oe_workflow_demo',
      'title' => 'My node',
      'moderation_state' => 'draft',
      'status' => 0,
    ]);

    $user = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($user);

    \Drupal::state()->set('oe_editorial_corporate_workflow_test_track_entity_state_changes', TRUE);

    $this->drupalGet($node->toUrl());
    $this->getSession()->getPage()->selectFieldOption('Change to', 'published');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();

    $states = \Drupal::state()->get('oe_editorial_corporate_workflow_test_entity_states', []);
    $this->assertEquals([
      ['original' => 'draft', 'new' => 'needs_review'],
      ['original' => 'needs_review', 'new' => 'request_validation'],
      ['original' => 'request_validation', 'new' => 'validated'],
      ['original' => 'validated', 'new' => 'published'],
    ], $states[$node->id()]);

    // Start a new draft and go through the same process to assert we get the
    // same state flow.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Demo My node has been updated.');
    // Reset the tracker.
    \Drupal::state()->set('oe_editorial_corporate_workflow_test_entity_states', []);
    $this->getSession()->getPage()->selectFieldOption('Change to', 'published');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();

    \Drupal::state()->resetCache();
    $states = \Drupal::state()->get('oe_editorial_corporate_workflow_test_entity_states', []);
    $this->assertEquals([
      ['original' => 'draft', 'new' => 'needs_review'],
      ['original' => 'needs_review', 'new' => 'request_validation'],
      ['original' => 'request_validation', 'new' => 'validated'],
      ['original' => 'validated', 'new' => 'published'],
    ], $states[$node->id()]);

  }

}
