<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_corporate_workflow\Services;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for shortcut revision handlers.
 *
 * These are used in the form responsible for moderating content, provided
 * by Drupal core.
 */
interface ShortcutRevisionHandlerInterface {

  /**
   * Saves all the revisions for the transitions between two states.
   *
   * This is needed because we do not want to skip the creation of the revisions
   * that would have otherwise been created had we not used the shortcuts
   * to skip transitions.
   *
   * @param string $target_state
   *   The desired workflow state.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $revision_message
   *   The revision log message.
   */
  public function createShortcutRevisions(string $target_state, ContentEntityInterface $entity, string $revision_message = NULL);

}
