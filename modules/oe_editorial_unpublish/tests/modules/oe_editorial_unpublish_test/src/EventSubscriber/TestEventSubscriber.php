<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_unpublish_test\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\State\StateInterface;
use Drupal\oe_editorial_unpublish\Event\UnpublishStatesEvent;
use Drupal\workflows\StateInterface as WorkflowStateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber that alters the list of the states that unpublish content.
 */
class TestEventSubscriber implements EventSubscriberInterface {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * The state system.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * TestEventSubscriber constructor.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state system.
   */
  public function __construct(ModerationInformationInterface $moderation_info, StateInterface $state) {
    $this->moderationInfo = $moderation_info;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [UnpublishStatesEvent::EVENT_NAME => 'removeState'];
  }

  /**
   * Removes the "Expired" state from the list.
   *
   * @param \Drupal\oe_editorial_unpublish\Event\UnpublishStatesEvent $event
   *   The event.
   */
  public function removeState(UnpublishStatesEvent $event): void {
    if ($this->state->get('oe_editorial_unpublish_test_remove_state', FALSE) === FALSE) {
      return;
    }

    // Create a dummy state and add it to the event list.
    $states = array_filter($event->getStates(), function (WorkflowStateInterface $state, string $id) {
      return $id !== 'archived';
    }, ARRAY_FILTER_USE_BOTH);
    $event->setStates($states);
  }

}
