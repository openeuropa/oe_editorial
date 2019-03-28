<?php

namespace Drupal\oe_editorial_corporate_workflow;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Validates whether a certain state transition is allowed.
 */
class StateTransitionValidation implements StateTransitionValidationInterface {

  /**
   * The content moderation state transition validation service.
   *
   * @var \Drupal\content_moderation\StateTransitionValidationInterface
   */
  protected $inner;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs the group state transition validation object.
   *
   * @param \Drupal\content_moderation\StateTransitionValidationInterface $inner
   *   The content moderation state transition validation service.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(StateTransitionValidationInterface $inner, ModerationInformationInterface $moderation_information, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager) {
    $this->inner = $inner;
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationInformation = $moderation_information;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function getValidTransitions(ContentEntityInterface $entity, AccountInterface $user) {
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    $current_state = $entity->moderation_state->value ? $workflow->getTypePlugin()->getState($entity->moderation_state->value) : $workflow->getTypePlugin()->getInitialState($entity);

    return $this->getNextTransition($current_state, $entity);
  }

  /**
   * @param $current_state
   * @param $entity
   * @param null $next_transitions
   * @return |null
   */
  public function getNextTransition($current_state, $entity, &$next_transitions = NULL) {
    $transitions = $current_state->getTransitions();
    $next_transition = end($transitions);
    $next_state = $next_transition->to();

    if ($next_state->id() != $entity->moderation_state->value) {
      $next_transitions[$next_transition->id()] = $next_transition;
      $this->getNextTransition($next_state, $entity, $next_transitions);
    }

    return $next_transitions;
  }

  /**
   * {@inheritdoc}
   */
  public function isTransitionValid(WorkflowInterface $workflow, StateInterface $original_state, StateInterface $new_state, AccountInterface $user, ContentEntityInterface $entity = NULL) {
    return TRUE;
  }

}
