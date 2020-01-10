<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_corporate_workflow_translation\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_translation_poetry\Event\PoetryRequestTypeEvent;
use Drupal\oe_translation_poetry\Plugin\tmgmt\Translator\PoetryTranslator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the event that determines the Poetry request type.
 *
 * The request type can either be for a new translation or an update
 * and we only allow the update if there is actually a change in the content.
 */
class PoetryRequestTypeSubscriber implements EventSubscriberInterface {

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
    return [PoetryRequestTypeEvent::EVENT => 'getRequestType'];
  }

  /**
   * Determine the request type.
   *
   * We only want to allow the update if the entity has reached another version.
   *
   * @param \Drupal\oe_translation_poetry\Event\PoetryRequestTypeEvent $event
   *   The event.
   */
  public function getRequestType(PoetryRequestTypeEvent $event) {
    if ($event->getRequestType() === PoetryTranslator::POETRY_REQUEST_NEW) {
      // If it's already a request for a new translation, we are fine with that.
      return;
    }

    $entity = $event->getEntity();
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      // If the content is not using the corporate workflow, we don't care.
      return;
    }

    // Determine the version of entity when the request was first made. If the
    // request is one of update, it means we do have job information available
    // to inspect.
    $job_info = $event->getJobInfo();
    $job_item = $this->entityTypeManager->getStorage('tmgmt_job_item')->load($job_info->tjiid);
    $entity_storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $revision_id = $job_item->get('item_rid')->value;
    $original_revision = $entity_storage->loadRevision($revision_id);
    $original_major = (int) $original_revision->get('version')->first()->getValue()['major'];
    $current_major = (int) $entity_storage->loadRevision($entity_storage->getLatestRevisionId($entity->id()))->get('version')->first()->getValue()['major'];;
    if ($current_major <= $original_major) {
      // If the version of the current entity is not higher, we don't allow
      // updates.
      $event->setRequestType(PoetryTranslator::POETRY_REQUEST_NEW);
    }
  }

}
