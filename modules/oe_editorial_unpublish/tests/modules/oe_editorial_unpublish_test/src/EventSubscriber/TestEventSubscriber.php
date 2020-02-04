<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_unpublish_test\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\oe_editorial_unpublish\Event\UnpublishStatesEvent;
use Drupal\workflows\State;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to unpublish state events and adds a test state to the list.
 *
 * @package Drupal\oe_editorial_unpublish_test\EventSubscriber
 */
class TestEventSubscriber implements EventSubscriberInterface {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * TestEventSubscriber constructor.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   */
  public function __construct(ModerationInformationInterface $moderation_info) {
    $this->moderationInfo = $moderation_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [UnpublishStatesEvent::EVENT_NAME => 'addState'];
  }

  /**
   * React to the list of unpublishable states being created.
   *
   * @param \Drupal\oe_editorial_unpublish\Event\UnpublishStatesEvent $event
   *   Config crud event.
   */
  public function addState(UnpublishStatesEvent $event) {
    // Create a dummy state and add it to the event list.
    $workflow = $this->moderationInfo->getWorkflowForEntityTypeAndBundle('node', 'oe_workflow_demo');
    $state = new State($workflow->getTypePlugin(), 'test', 'Test');
    $event->addState($state);
  }

}
