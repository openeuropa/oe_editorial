<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_corporate_workflow;

use Drupal\content_moderation\StateTransitionValidation;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\TransitionInterface;

/**
 * Custom override of the StateTransitionValidation service.
 *
 * Allows transitions to all the possible states the current user can do.
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

    // Prepare the list of available transitions by checking that the user has
    // access to them. If encountering one without access, we break so that
    // we do not include any of the transitions that follow it in the chain.
    foreach ($next_transitions as $key => $transition) {
      if (!$user->hasPermission('use ' . $workflow->id() . ' transition ' . $transition->id())) {
        break;
      }
      $valid_transitions[$key] = $transition;
    }

    uasort($valid_transitions, function (TransitionInterface $a, TransitionInterface $b) {
      $a_weight = $a->to()->weight();
      $b_weight = $b->to()->weight();

      return $a_weight <=> $b_weight;
    });

    return $valid_transitions;
  }

  /**
   * Get the next transition in the workflow chain based on the actual state.
   *
   * This only works in one direction: next in the chain and never back.
   * The chain ends whenever one of the following conditions are met:
   * - the next transition is to a state equal to the current entity state
   * - the next transition is to the Expired state while the current entity
   * state is Archived. This is because the two are on the same level in the
   * chain.
   *
   * @param \Drupal\workflows\StateInterface $current_state
   *   The actual state.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity under moderation.
   * @param array $next_transitions
   *   The next available transitions in the chain that we keep track of by
   *   recursion.
   *
   * @return array
   *   The next available transitions in the chain.
   */
  protected function getNextTransitions(StateInterface $current_state, ContentEntityInterface $entity, array &$next_transitions = []): array {
    $transitions = $current_state->getTransitions();
    if (empty($next_transitions)) {
      $next_transitions = $transitions;
    }

    // This is the next transition in the chain. However, for the Archived and
    // Expired states, the transitions are at the same level so the Expired
    // one will be considered as the next one and never the Archived. This is
    // because of alphabetical ordering.
    $next_transition = end($transitions);
    $next_state = $next_transition->to();

    if ($next_state->id() === 'expired') {
      // The transition to Expired is already included in the recursion below so
      // we just need to include the transition to Archived as well in the list
      // of possible next transitions.
      $next_transitions['published_to_archived'] = $transitions['published_to_archived'];

      if ($entity->moderation_state->value === 'archived') {
        // If the current state is either Archived or Expired, it means we
        // reached the end of the chain and return the transitions.
        return $next_transitions;
      }
    }

    if ($next_state->id() === $entity->moderation_state->value) {
      // If the next state is the same as the current state, it means we reached
      // the end of the chain and caught up with the current state again. In
      // this case we return the next transitions.
      return $next_transitions;
    }

    // Add the next transition to the list of available transitions.
    $next_transitions[$next_transition->id()] = $next_transition;

    // If the next state is not the same as the current state, then we recurse
    // to retrieve the next transitions in the chain until we reach the
    // starting point again. See above the possible end points.
    return $this->getNextTransitions($next_state, $entity, $next_transitions);
  }

}
