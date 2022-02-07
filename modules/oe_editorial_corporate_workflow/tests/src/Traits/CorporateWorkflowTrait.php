<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_editorial_corporate_workflow\Traits;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Traits for testing the corporate editorial workflow.
 */
trait CorporateWorkflowTrait {

  /**
   * Returns the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager ?? \Drupal::entityTypeManager();
  }

  /**
   * Sends the node through the moderation states to reach the target.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $target_state
   *   The target moderation state,.
   *
   * @return \Drupal\node\NodeInterface
   *   The latest node revision.
   */
  protected function moderateNode(NodeInterface $node, string $target_state): NodeInterface {
    $states = [
      'draft',
      'needs_review',
      'request_validation',
      'validated',
      'published',
    ];

    $current_state = $node->get('moderation_state')->value;
    if ($current_state === $target_state) {
      return $node;
    }

    $pos = array_search($current_state, $states);
    foreach (array_slice($states, $pos + 1) as $new_state) {
      $node = $revision ?? $node;
      $revision = $this->getEntityTypeManager()->getStorage('node')->createRevision($node);
      $revision->set('moderation_state', $new_state);
      $revision->save();
      if ($new_state === $target_state) {
        return $revision;
      }
    }

    return $revision ?? $node;
  }

}
