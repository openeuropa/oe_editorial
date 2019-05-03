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
class OeStateTransitionValidation extends StateTransitionValidation implements StateTransitionValidationInterface {

  /**
   * {@inheritdoc}
   */
  public function getValidTransitions(ContentEntityInterface $entity, AccountInterface $user): array {
    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
    $current_state = $entity->moderation_state->value ? $workflow->getTypePlugin()->getState($entity->moderation_state->value) : $workflow->getTypePlugin()->getInitialState($entity);

    return $entity->isNew() || $entity->isNewRevision() ? $current_state->getTransitions() : $this->getNextTransitions($current_state, $entity);
  }

  /**
   * Get the next state up in the workflow chain based on the actual state.
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
    $next_transition = end($transitions);
    $next_state = $next_transition->to();

    if ($next_state->id() != $entity->moderation_state->value) {
      $next_transitions[$next_transition->id()] = $next_transition;

      // Exception to include Archive beside Expired state.
      if (isset($transitions['published_to_archived'])) {
        $next_transitions['published_to_archived'] = $transitions['published_to_archived'];
      }

      $this->getNextTransitions($next_state, $entity, $next_transitions);
    }

    return $next_transitions;
  }

}
