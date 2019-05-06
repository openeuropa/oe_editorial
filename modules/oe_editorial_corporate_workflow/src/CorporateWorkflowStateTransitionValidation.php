<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_corporate_workflow;

use Drupal\content_moderation\StateTransitionValidation;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\StateInterface;

/**
 * Override StateTransitionValidation.
 */
class CorporateWorkflowStateTransitionValidation extends StateTransitionValidation implements StateTransitionValidationInterface {

  /**
   * {@inheritdoc}
   */
  public function getValidTransitions(ContentEntityInterface $entity, AccountInterface $user): array {
    $valid_transitions = [];
    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
    $current_state = $entity->moderation_state->value ? $workflow->getTypePlugin()->getState($entity->moderation_state->value) : $workflow->getTypePlugin()->getInitialState($entity);
    $next_transitions = $this->getNextTransitions($current_state, $entity);

    // Look for permission gap in the transition chain and leave the valid ones.
    foreach ($next_transitions as $key => $transition) {
      if (!$user->hasPermission('use ' . $workflow->id() . ' transition ' . $transition->id())) {
        break;
      }
      $valid_transitions[$key] = $transition;
    }

    return $valid_transitions;
  }

  /**
   * Get the next transition up in the workflow chain based on the actual state.
   *
   * @param \Drupal\workflows\StateInterface $current_state
   *   The actual state.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity under moderation.
   * @param null|array $next_transitions
   *   The next available transitions in the chain.
   *
   * @return array
   *   The next available transitions in the chain.
   */
  protected function getNextTransitions(StateInterface $current_state, ContentEntityInterface $entity, &$next_transitions = NULL): array {
    $transitions = $current_state->getTransitions();
    if (empty($next_transitions)) {
      $next_transitions = $transitions;
    }
    $upcoming_transition = end($transitions);
    $upcoming_state = $upcoming_transition->to();

    if ($upcoming_state->id() != $entity->moderation_state->value) {
      $next_transitions[$upcoming_transition->id()] = $upcoming_transition;

      // Exception to include Archive with Expired state.
      if (isset($transitions['published_to_archived'])) {
        $next_transitions['published_to_archived'] = $transitions['published_to_archived'];
      }

      $this->getNextTransitions($upcoming_state, $entity, $next_transitions);
    }

    return $next_transitions;
  }

}
