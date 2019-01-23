<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial\Behat;

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines step definitions for testing the Content Lock feature.
 */
class ContentLockContext extends RawDrupalContext {

  /**
   * Creates content authored by the current user.
   *
   * @Given I am viewing my :type_name content titled :title
   */
  public function createMyNode($type_name, $title): void {
    if ($this->getUserManager()->currentUserIsAnonymous()) {
      throw new \Exception(sprintf('There is no current logged in user to create a node for.'));
    }

    $content_type = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadByProperties(['name' => $type_name]);

    if (empty($content_type)) {
      throw new \Exception(sprintf('No %s content type found.', $type_name));
    }
    /** @var \Drupal\node\Entity\NodeType $content_type */
    $content_type = reset($content_type);
    $node = (object) [
      'title' => $title,
      'type' => $content_type->get('type'),
      'body' => $this->getRandom()->name(255),
      'uid' => $this->getUserManager()->getCurrentUser()->uid,
    ];
    $saved = $this->nodeCreate($node);

    // Set internal page on the new node.
    $this->getSession()->visit($this->locatePath('/node/' . $saved->nid));
  }

  /**
   * Install the content_lock component.
   *
   * @BeforeScenario @content_lock
   */
  public function enableContentLock(): void {
    // Enable the Content Lock feature.
    \Drupal::service('module_installer')->install(['oe_editorial_content_lock']);
  }

  /**
   * Uninstall the content_lock component.
   *
   * @AfterScenario @content_lock
   */
  public function disableContentLock(): void {
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
