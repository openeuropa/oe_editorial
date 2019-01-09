<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial\Behat;

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines step definitions for testing the Content Lock feature.
 */
class ContentLockContext extends RawDrupalContext {

  /**
   * Install the content_lock component.
   *
   * @BeforeScenario @content_lock
   */
  public function enableContentLock() {
    // Enable the Content Lock feature.
    \Drupal::service('module_installer')->install(['oe_editorial_content_lock']);
  }

  /**
   * Uninstall the content_lock component.
   *
   * @AfterScenario @content_lock
   */
  public function disableContentLock() {
    $modules = [
      'oe_editorial_content_lock',
      'content_lock',
    ];
    // Remove the Content Lock feature and its dependencies.
    foreach ($modules as $module) {
      \Drupal::service('module_installer')->uninstall([$module]);
    }
  }

}
