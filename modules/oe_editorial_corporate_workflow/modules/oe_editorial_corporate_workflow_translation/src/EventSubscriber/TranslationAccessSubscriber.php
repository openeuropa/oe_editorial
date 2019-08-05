<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_corporate_workflow_translation\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_translation\Event\TranslationAccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Determines access to create new translations based on the corporate workflow.
 */
class TranslationAccessSubscriber implements EventSubscriberInterface {

  /**
   * The moderation info.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * TranslationAccessSubscriber constructor.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The moderation info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(ModerationInformationInterface $moderationInformation, EntityTypeManagerInterface $entityTypeManager) {
    $this->moderationInformation = $moderationInformation;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      TranslationAccessEvent::EVENT => 'access',
    ];
  }

  /**
   * Callback to control the access.
   *
   * Entities using the corporate workflow can only be translated in validated.
   *
   * @param \Drupal\oe_translation\Event\TranslationAccessEvent $event
   *   The event.
   */
  public function access(TranslationAccessEvent $event) {
    $entity = $event->getEntity();
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      return;
    }

    if (!$entity->isLatestRevision()) {
      // We only allow access if the latest entity revision qualifies below.
      $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      $entity = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));
    }

    // Content can only be translated when reaching the validated state.
    $state = $entity->get('moderation_state')->value;
    if (!in_array($state, ['validated', 'published'])) {
      $event->setAccess(AccessResult::forbidden());
      return;
    }
  }

}
