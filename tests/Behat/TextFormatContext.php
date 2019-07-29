<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial\Behat;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Gherkin\Node\TableNode;
use Drupal\filter\Entity\FilterFormat;
use Drupal\user\Entity\Role;

/**
 * Defines step definitions for testing text formats.
 */
class TextFormatContext extends RawDrupalContext {

  /**
   * The IDs of text formats created during tests.
   *
   * @var array
   */
  protected $newTextFormats = [];

  /**
   * Deletes text formats created through the scenario.
   *
   * @param \Behat\Behat\Hook\Scope\AfterScenarioScope $scope
   *   The scenario scope.
   *
   * @afterScenario @cleanup-formats
   */
  public function cleanupCreatedTextFormats(AfterScenarioScope $scope): void {
    $basic_roles = [
      'anonymous',
      'authenticated',
    ];
    foreach ($this->newTextFormats as $text_format_id) {
      foreach ($basic_roles as $role) {
        $role_object = Role::load($role);
        $role_object->revokePermission('use text format ' . $text_format_id);
        $role_object->save();
      }
      $text_format = FilterFormat::load($text_format_id);
      $text_format->delete();
    }
  }

  /**
   * Checks the given node with title has the specified version numbers.
   *
   * | id   | title   |
   * | id 1 | title 1 |
   * | id 2 | title 2 |
   *
   * @Then the following text formats are available:
   */
  public function textFormatsAreAvailable(TableNode $formats) {
    $basic_roles = [
      'anonymous',
      'authenticated',
    ];
    foreach ($formats->getHash() as $format) {
      $filter_format = FilterFormat::create([
        'name' => $format['title'],
        'format' => $format['id'],
      ]);
      $filter_format->save();
      $this->newTextFormats[] = $format['id'];

      foreach ($basic_roles as $role) {
        $role_object = Role::load($role);
        $role_object->grantPermission('use text format ' . $format['id']);
        $role_object->save();
      }
    }
  }

}
