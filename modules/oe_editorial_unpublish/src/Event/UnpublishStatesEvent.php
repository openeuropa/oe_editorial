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
   * The states available for unpublishing.
   *
   * @var \Drupal\workflows\StateInterface
   */
  protected $states;

  /**
   * Constructs the object.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being checked.
   * @param \Drupal\workflows\StateInterface[] $states
   *   The current list of available states.
   */
  public function __construct(NodeInterface $node, array $states) {
    $this->node = $node;
    $this->states = $states;
  }

  /**
   * Set available states for unpublishing.
   *
   * @param array $states
   *   The available states.
   */
  public function setStates(array $states): void {
    $this->states = $states;
  }

  /**
   * Get available states for unpublishing.
   *
   * @return array|\Drupal\workflows\StateInterface
   *   The states available to unpublish into.
   */
  public function getStates(): array {
    return $this->states;
  }

  /**
   * Add an available state for unpublishing.
   *
   * @param \Drupal\workflows\StateInterface $state
   *   The new available state.
   */
  public function addState(StateInterface $state): void {
    $this->states[$state->id()] = $state;
  }

  /**
   * Add a set of available states for unpublishing.
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
