<?php

declare(strict_types=1);

namespace Drupal\oe_editorial_unpublish\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event for altering the list of available states that unpublish an entity.
 */
class UnpublishStatesEvent extends Event {

  /**
   * The event name.
   */
  const EVENT_NAME = 'oe_editorial_unpublish.unpublish_states_event';

  /**
   * The entity being unpublished.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The available states that unpublish the entity.
   *
   * @var \Drupal\workflows\StateInterface
   */
  protected $states;

  /**
   * Constructs an instance of UnpublishStatesEvent.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being unpublished.
   * @param \Drupal\workflows\StateInterface[] $states
   *   The current list of available states.
   */
  public function __construct(ContentEntityInterface $entity, array $states) {
    $this->entity = $entity;
    $this->states = $states;
  }

  /**
   * Set available states for unpublishing.
   *
   * @param \Drupal\workflows\StateInterface[] $states
   *   The available states.
   */
  public function setStates(array $states): void {
    $this->states = $states;
  }

  /**
   * Get available states for unpublishing.
   *
   * @return \Drupal\workflows\StateInterface[]
   *   The states available to unpublish into.
   */
  public function getStates(): array {
    return $this->states;
  }

  /**
   * Get the entity the states apply to.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity being unpublished.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

}
