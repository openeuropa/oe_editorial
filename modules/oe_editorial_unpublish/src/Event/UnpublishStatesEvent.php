<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_unpublish\Event;

use Drupal\node\NodeInterface;
use Drupal\workflows\StateInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event for altering the list of available states to unpublish a node into.
 */
class UnpublishStatesEvent extends Event {

  const EVENT_NAME = 'unpublish_states_event';

  /**
   * The node the states apply to.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The states available to unpublish into.
   *
   * @var \Drupal\workflows\StateInterface
   */
  protected $states;

  /**
   * Constructs the object.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The account of the user logged in.
   * @param \Drupal\workflows\StateInterface[] $states
   *   The account of the user logged in.
   */
  public function __construct(NodeInterface $node, array $states) {
    $this->node = $node;
    $this->states = $states;
  }

  /**
   * Set states available to unpublish into.
   *
   * @param array $states
   *   The available states.
   */
  public function setStates(array $states): void {
    $this->states = $states;
  }

  /**
   * Get the states available to unpublish into.
   *
   * @return array|\Drupal\workflows\StateInterface
   *   The states available to unpublish into.
   */
  public function getStates(): array {
    return $this->states;
  }

  /**
   * Add a state available to unpublish into.
   *
   * @param \Drupal\workflows\StateInterface $state
   *   The new available state.
   */
  public function addState(StateInterface $state): void {
    $this->states[$state->id()] = $state;
  }

  /**
   * Add a set of states available to unpublish into.
   *
   * @param array $states
   *   The new available states.
   */
  public function addStates(array $states): void {
    foreach ($states as $state) {
      $this->addState($state);
    }
  }

  /**
   * Get the node the states apply to.
   *
   * @return \Drupal\node\NodeInterface
   *   The node the states apply to.
   */
  public function getNode(): NodeInterface {
    return $this->node;
  }

}
