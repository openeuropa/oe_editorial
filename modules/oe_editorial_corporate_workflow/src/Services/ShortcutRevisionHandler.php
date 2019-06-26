<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_corporate_workflow\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\workflows\WorkflowTypeInterface;

/**
 * Handler for creating state transition revisions when shortcuts are used.
 */
class ShortcutRevisionHandler implements ShortcutRevisionHandlerInterface {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * The current user logged in.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new ShortcutRevisionHandler.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user logged in.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager to retrieve storage.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time object.
   */
  public function __construct(ModerationInformationInterface $moderation_info, AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    $this->moderationInfo = $moderation_info;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function createShortcutRevisions(string $target_state, ContentEntityInterface $entity, string $revision_message = NULL): RevisionableInterface {
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
    /** @var \Drupal\workflows\WorkflowTypeInterface $workflow_plugin */
    $workflow_plugin = $workflow->getTypePlugin();

    // We start the saving from the current state of the entity and we proceed
    // until the target state that was selected from the moderation form.
    $current_state = $entity->get('moderation_state')->value;

    return $this->saveTransitionRevisions($current_state, $target_state, $workflow_plugin, $entity, $revision_message);
  }

  /**
   * Recursively saves the revisions between two states.
   *
   * @param string $current_state
   *   The current state of the entity.
   * @param string $target_state
   *   The target state selected from the moderation form.
   * @param \Drupal\workflows\WorkflowTypeInterface $workflow_plugin
   *   The workflow type plugin.
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   The actual entity that we are saving revisions for.
   * @param string $revision_message
   *   The revision log message.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface
   *   Return the revisioned entity.
   */
  protected function saveTransitionRevisions($current_state, $target_state, WorkflowTypeInterface $workflow_plugin, RevisionableInterface $entity, $revision_message): RevisionableInterface {
    // We need to stop before the last transition because the creation of that
    // revision is handled by core.
    if ($workflow_plugin->hasTransitionFromStateToState($current_state, $target_state)) {
      return $entity;
    }

    // Take next transition in the chain.
    $transitions = $workflow_plugin->getTransitionsForState($current_state);
    /** @var \Drupal\workflows\TransitionInterface $next_transition */
    $next_transition = end($transitions);

    // Create a new revision for the transition change and save the entity.
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $entity = $storage->createRevision($entity, $entity->isDefaultRevision());
    // Set the next state id.
    $entity->set('moderation_state', $next_transition->to()->id());

    // Ensure that we are carrying over the revision log message.
    if ($entity instanceof RevisionLogInterface) {
      $entity->setRevisionCreationTime($this->time->getRequestTime());
      if ($revision_message) {
        $entity->setRevisionLogMessage($revision_message);
      }
      $entity->setRevisionUserId($this->currentUser->id());
    }
    $entity->save();

    // We need to repeat this operation until we reach the target state.
    return $this->saveTransitionRevisions($entity->get('moderation_state')->value, $target_state, $workflow_plugin, $entity, $revision_message);
  }

}
