<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial\Behat;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Element\NodeElement;
use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;

/**
 * Defines step definitions that are generally useful in this project.
 */
class FeatureContext extends RawDrupalContext {

  /**
   * Keep track of modules so they can be cleaned up.
   *
   * @var array
   */
  protected $modules = [];

  /**
   * Checks that the given select field has the options listed in the table.
   *
   * // phpcs:disable
   * @Then I should have the following options for the :select select:
   * | option 1 |
   * | option 2 |
   * |   ...    |
   * // phpcs:enable
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

    // Check that all expected options are present.
    foreach ($expected_options as $expected_option) {
      if (!in_array($expected_option, $actual_options)) {
        throw new ExpectationException("Option '$expected_option' is missing from select list '$select'.", $this->getSession());
      }
    }
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
    $element = $this->getSession()->getPage()->find('css', 'div#edit-moderation-state-0-current');
    if (empty($element)) {
      throw new \Exception('The current workflow state field is not present on the page.');
    }
    // Need to remove the label from the string.
    Assert::assertEquals($state, ltrim(trim($element->getText()), 'Current state'));
  }

  /**
   * Checks that the given element is of the given type.
   *
   * @param \NodeElement $element
   *   The element to check.
   * @param string $type
   *   The expected type.
   *
   * @throws \ExpectationException
   *   Thrown when the given element is not of the expected type.
   */
  protected function assertElementType(NodeElement $element, string $type): void {
    if ($element->getTagName() !== $type) {
      throw new ExpectationException("The element is not a '$type'' field.", $this->getSession());
    }
  }

  /**
   * Enables a module.
   *
   * @param string $module
   *   The module to be enabled.
   *
   * @Given the module(s) :module is/are enabled
   */
  public function theModuleIsEnabled($module) {
    $modules = explode(',', $module);
    $modules = array_map('trim', $modules);
    foreach ($modules as $module) {
      \Drupal::service('module_installer')->install([$module]);
      \Drupal::service('config.installer')->installDefaultConfig('module', $module);
      $this->modules[] = $module;
    }
  }

  /**
   * Uninstall any installed module.
   *
   * @AfterScenario
   */
  public function cleanModules() {
    // Revert config that was changed.
    foreach ($this->modules as $module) {
      \Drupal::service('module_installer')->uninstall([$module]);
    }
    $this->modules = [];
  }

  /**
   * Remove any created nodes.
   *
   * @AfterScenario
   */
  public function cleanNodes() {
    // Remove any nodes that were created.
    foreach ($this->nodes as $node) {
      \Drupal::service('content_lock')->uninstall([$node->id]);
      $this->getDriver()->nodeDelete($node);
    }
    $this->nodes = [];
  }

}
