<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_corporate_workflow;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Batch methods to transition an entity to a final state.
 */
class EntityStateTransitionBatch implements ContainerInjectionInterface {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Create a new EntityStateTransitionBatch object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The moderation information service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModerationInformationInterface $moderationInformation, TimeInterface $time, AccountInterface $currentUser, MessengerInterface $messenger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->moderationInformation = $moderationInformation;
    $this->time = $time;
    $this->currentUser = $currentUser;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('content_moderation.moderation_information'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('messenger')
    );
  }

  /**
   * Executes a single transition to a state, creating a new entity revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being transitioned.
   * @param array $transitions
   *   The original list of transitions to execute.
   * @param string $revision_log_message
   *   The revision log message.
   * @param mixed $context
   *   The batch context.
   */
  public function execute(ContentEntityInterface $entity, array $transitions, string $revision_log_message, &$context): void {
    // Initialise the sandbox if needed.
    if (!isset($context['sandbox']['current_revision'])) {
      $context['sandbox']['current_revision'] = $entity;
      $context['sandbox']['transitions'] = $transitions;
      $context['sandbox']['total'] = count($transitions);
    }

    $entity = $context['sandbox']['current_revision'];
    $to_state = array_shift($context['sandbox']['transitions']);

    // Create a new revision for the transition change and save the entity.
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $entity = $storage->createRevision($entity, $entity->isDefaultRevision());
    // Set the next state id.
    $entity->set('moderation_state', $to_state);

    if ($entity instanceof RevisionLogInterface) {
      $entity->setRevisionCreationTime($this->time->getRequestTime());
      $entity->setRevisionLogMessage($revision_log_message);
      $entity->setRevisionUserId($this->currentUser->id());
    }
    $entity->save();

    $context['sandbox']['current_revision'] = $entity;

    // If no more transitions are available, store the final revision in the
    // results.
    if (empty($context['sandbox']['transitions'])) {
      $context['results']['current_revision'] = $entity;
    }

    $context['finished'] = 1 - (count($context['sandbox']['transitions']) / $context['sandbox']['total']);
  }

  /**
   * Finishes the batch.
   *
   * @param bool $success
   *   The success status of the batch.
   * @param array $results
   *   A list of results.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect to the canonical entity page if the last state qualifies.
   */
  public function finish(bool $success, array $results): ?RedirectResponse {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $results['current_revision'];

    $this->messenger->addStatus($this->t('The moderation state has been updated.'));

    $workflow_plugin = $this->moderationInformation->getWorkflowForEntity($entity)->getTypePlugin();
    $state = $workflow_plugin->getState($entity->get('moderation_state')->value);
    // The page we're on likely won't be visible if we just set the entity to
    // the default state, as we hide that latest-revision tab if there is no
    // pending revision. Redirect to the canonical URL instead, since that will
    // still exist.
    if ($state->isDefaultRevisionState()) {
      return new RedirectResponse($entity->toUrl('canonical')->setAbsolute()->toString());
    }

    return NULL;
  }

}
