<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial\Behat;

use Drupal\DrupalExtension\Context\DrupalContext;
use PHPUnit\Framework\Assert;

/**
 * Defines step definitions for testing the Content Lock feature.
 */
class ContentLockContext extends DrupalContext {

  /**
   * Creates content authored by the current user.
   *
   * @throws \Exception
   *   Throws an exception when the content type or the node are not found.
   *
   * @Given I am viewing my :type_name content titled :title
   */
  public function createMyNode($type_name, $title): void {
    if ($this->getUserManager()->currentUserIsAnonymous()) {
      throw new \Exception('There is no current logged in user to create a node for.');
    }

    $content_type = $this->getContentTypeFromName($type_name);
    $node = (object) [
      'title' => $title,
      'type' => $content_type,
      'body' => $this->getRandom()->name(255),
      'uid' => $this->getUserManager()->getCurrentUser()->uid,
    ];
    $saved = $this->nodeCreate($node);

    // Set internal page on the new node.
    $this->getSession()->visit($this->locatePath('/node/' . $saved->nid));
  }

  /**
   * Navigates to a particular node..
   *
   * @param string $type_name
   *   The content type of the node.
   * @param string $title
   *   The title of the node.
   *
   * @throws \Exception
   *   Thrown when node is not found.
   *
   * @Then I visit the :type_name content titled :title
   */
  public function visitContentWithTitle($type_name, $title): void {

    $content_type = $this->getContentTypeFromName($type_name);

    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['title' => $title]);
    /** @var \Drupal\node\Entity\Node $node */
    foreach ($nodes as $node) {
      if ($node->getType() == $content_type) {
        $found = $node;
        break;
      }
    }
    if (empty($found)) {
      throw new \Exception(sprintf('No %s content type with title %s found.', $type_name, $title));
    }
    // Set internal page on the new node.
    $this->getSession()->visit($this->locatePath('/node/' . $found->id()));
  }

  /**
   * Asserts the button with specified id|name|title|alt|value is disabled.
   *
   * @throws \Exception
   *   Thrown when the button is not found.
   *
   * @Then the :button button is disabled
   */
  public function buttonIsDisabled($button) {
    $button_element = $this->getSession()->getPage()->findButton($button);
    if (empty($button_element)) {
      throw new \Exception(sprintf('Button %s was not found.', $button));
    }
    $disabled = $button_element->getAttribute('disabled');
    // Need to remove the label from the string.
    Assert::assertEquals($disabled, 'disabled', 'Disabled state');
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

  /**
   * Remove any created nodes.
   *
   * @AfterScenario
   */
  public function cleanNodes(): void {
    if (\Drupal::moduleHandler()->moduleExists('content_lock')) {
      \Drupal::database()->delete('content_lock')
        ->execute();
    }
    parent::cleanNodes();
  }

  /**
   * Returns the machine name of a content type from the readable name.
   *
   * @param string $type_name
   *   Readable name of the content type.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return string
   *   Machine name of the content type.
   */
  private function getContentTypeFromName($type_name): string {
    $content_type = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadByProperties(['name' => $type_name]);

    if (empty($content_type)) {
      throw new \Exception(sprintf('No %s content type found.', $type_name));
    }
    /** @var \Drupal\node\Entity\NodeType $content_type */
    $content_type = reset($content_type);
    return (string) $content_type->get('type');
  }

}
