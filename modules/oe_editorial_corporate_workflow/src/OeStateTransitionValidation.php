<?php

namespace Drupal\oe_editorial_corporate_workflow;

use Drupal\content_moderation\StateTransitionValidation;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Override StateTransitionValidation.
 */
class OeStateTransitionValidation extends StateTransitionValidation implements StateTransitionValidationInterface {

  /**
   * {@inheritdoc}
   */
  public function getValidTransitions(ContentEntityInterface $entity, AccountInterface $user) {
    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
    $current_state = $entity->moderation_state->value ? $workflow->getTypePlugin()->getState($entity->moderation_state->value) : $workflow->getTypePlugin()->getInitialState($entity);


    return $entity->isNew() || $entity->isNewRevision() ? $current_state->getTransitions() : $this->getNextTransitions($current_state, $entity);
  }

  /**
   * @param $current_state
   * @param $entity
   * @param null $next_transitions
   * @return |null
   */
  public function getNextTransitions($current_state, $entity, &$next_transitions = NULL) {
    $transitions = $current_state->getTransitions();
    $next_transition = end($transitions);
    $next_state = $next_transition->to();

    if ($next_state->id() != $entity->moderation_state->value) {
      $next_transitions[$next_transition->id()] = $next_transition;
      $this->getNextTransitions($next_state, $entity, $next_transitions);
    }

    return $next_transitions;
  }

}
