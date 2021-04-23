<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial\Behat;

use Behat\Mink\Exception\ResponseTextException;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Element\NodeElement;
use Behat\Gherkin\Node\TableNode;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Assert;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Defines step definitions that are generally useful in this project.
 */
class FeatureContext extends RawDrupalContext {

  /**
   * Checks that the given select field has the options listed in the table.
   *
   * | option 1 |
   * | option 2 |
   * |   ...    |
   *
   * @Then I should have the following options for the :select select:
   */
  public function assertSelectOptions(string $select, TableNode $options): void {
    // Retrieve the specified field.
    if (!$field = $this->getSession()->getPage()->findField($select)) {
      throw new ExpectationException("Field '$select' not found.", $this->getSession());
    }

    // Check that the specified field is a <select> field.
    $this->assertElementType($field, 'select');

    // Retrieve the options table from the test scenario and flatten it.
    $expected_options = $options->getRows();
    array_walk($expected_options, function (&$value) {
      $value = reset($value);
    });

    // Retrieve the actual options that are shown in the select field.
    $actual_options = $field->findAll('css', 'option');

    // Convert into a flat list of option text strings.
    array_walk($actual_options, function (&$value) {
      $value = $value->getText();
    });

    Assert::assertEquals($expected_options, $actual_options);
  }

  /**
   * Checks the current workflow state on an entity edit form.
   *
   * @param string $state
   *   The expected workflow state.
   *
   * @throws \Exception
   *   Thrown when the current workflow state field is not shown on the page.
   *
   * @Then the current workflow state should be :state
   */
  public function assertCurrentWorkflowState(string $state): void {
    // Find the content moderation form.
    $xpath = '//form[@class and contains(concat(" ", normalize-space(@class), " "), " content-moderation-entity-moderation-form ")]'
      // Target wrapper of the "Moderation state" label.
      . '//label[text()="Moderation state"]/..';
    $element = $this->getSession()->getPage()->find('xpath', $xpath);
    if (empty($element)) {
      throw new \Exception('The current workflow state field is not present on the page.');
    }

    // Selenium drivers cannot target text elements, so we need to find the
    // wanted text node by using the crawler.
    $crawler = new Crawler($element->getHtml());
    $state_text = $crawler->filterXPath('//label/following-sibling::text()')->text();

    Assert::assertEquals($state, trim($state_text));
  }

  /**
   * Checks that the given element is of the given type.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The element to check.
   * @param string $type
   *   The expected type.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   Thrown when the given element is not of the expected type.
   */
  protected function assertElementType(NodeElement $element, string $type): void {
    if ($element->getTagName() !== $type) {
      throw new ExpectationException("The element is not a '$type'' field.", $this->getSession());
    }
  }

  /**
   * Checks the given node with title has the number of revisions and states.
   *
   * | state 1 |
   * | state 2 |
   * |   ...   |
   *
   * @Then the node :title should have :number number of revisions with the following states:
   */
  public function theNodeShouldHaveNumberForRevisionsWithTheFollowingStates(string $title, int $number, TableNode $options) {
    $node = $this->getNodeByTitle($title);
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage($node->getEntityTypeId());
    $rids = $storage->revisionIds($node);

    if (count($rids) !== $number) {
      throw new ExpectationException('The number of revision doesn\'t match. Expected: ' . count($rids), $this->getSession());
    }

    // Retrieve the options table from the test scenario and flatten it.
    $expected_states = $options->getRows();
    array_walk($expected_states, function (&$value) {
      $value = reset($value);
    });

    /** @var \Drupal\content_moderation\ModerationInformationInterface $moderation_info */
    $moderation_info = \Drupal::service('content_moderation.moderation_information');
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $moderation_info->getWorkflowForEntity($node);
    /** @var \Drupal\workflows\WorkflowTypeInterface $workflow_plugin */
    $workflow_plugin = $workflow->getTypePlugin();

    // Retrieve the states from all the revisions and flatten it.
    $revision_states = [];
    foreach ($rids as $rid) {
      $revision = $storage->loadRevision($rid);
      $revision_states[] = $workflow_plugin->getState($revision->moderation_state->value)->label();
    }

    Assert::assertEquals($expected_states, $revision_states);
  }

  /**
   * Retrieves a node by its title.
   *
   * @param string $title
   *   The node title.
   *
   * @return \Drupal\node\NodeInterface
   *   The node entity.
   */
  protected function getNodeByTitle(string $title): NodeInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $nodes = $storage->loadByProperties([
      'title' => $title,
    ]);

    if (!$nodes) {
      throw new \Exception("Could not find node with title '$title'.");
    }

    if (count($nodes) > 1) {
      throw new \Exception("Multiple nodes with title '$title' found.");
    }

    return reset($nodes);
  }

  /**
   * Checks the given node with title has the specified version numbers.
   *
   * // phpcs:disable
   * @Then the node :title should have the following version:
   * | major | number 1 |
   * | minor | number 2 |
   * | patch | number 3 |
   * // phpcs:enable
   */
  public function assertNodeHasSpecificVersion(string $title, TableNode $options): void {
    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $nodes = $storage->loadByProperties([
      'title' => $title,
    ]);
    if (!$nodes) {
      throw new \Exception(sprintf('Could not find node with title "%s"', $title));
    }

    if (count($nodes) > 1) {
      throw new \Exception(sprintf('Multiple nodes with title "%s" found.', $title));
    }

    $node = reset($nodes);

    // Load the latest revision.
    $storage->resetCache();
    $node = $storage->loadRevision($storage->getLatestRevisionId($node->id()));
    $node_version_value = [
      'major' => $node->get('version')->major,
      'minor' => $node->get('version')->minor,
      'patch' => $node->get('version')->patch,
    ];
    Assert::assertEquals($options->getRowsHash(), $node_version_value);
  }

  /**
   * Check link to target.
   *
   * @param string $link
   *   Link identifier.
   * @param string $path
   *   Target path of the link.
   *
   * @throws \Exception
   *   Throws an exception if the link is not found or if the target is wrong.
   *
   * @Then I should see the link :link point to :path
   */
  public function assertLinkTarget(string $link, $path) {
    $target_url = $this->locatePath($path);
    $parts = parse_url($target_url);
    $expected_path = empty($parts['path']) ? '/' : $parts['path'];
    $page = $this->getSession()->getPage();
    $result = $page->findLink($link);
    if (empty($result)) {
      throw new \Exception("No link '{$link}' on the page");
    }

    $href = $result->getAttribute('href');
    if ($expected_path != $href) {
      throw new \Exception("The link '{$link}' points to '{$href}'");
    }
  }

  /**
   * Waits for some text to appear on the page.
   *
   * Note that the text asserted is case insensitive.
   *
   * @param string $text
   *   The text to wait for.
   *
   * @Then I wait for the text :text
   */
  public function waitForText(string $text): void {
    $page = $this->getSession()->getPage();
    $timeout = $this->getMinkParameter('ajax_timeout');

    $assert_session = $this->assertSession();
    $result = $page->waitFor($timeout, function () use ($assert_session, $text): bool {
      try {
        $assert_session->pageTextContains($text);
        return TRUE;
      }
      catch (ResponseTextException $exception) {
        return FALSE;
      }
    });

    if (!$result) {
      throw new \Exception(sprintf('The text "%s" was not found on the page after %d seconds.', $text, $timeout));
    }
  }

}
