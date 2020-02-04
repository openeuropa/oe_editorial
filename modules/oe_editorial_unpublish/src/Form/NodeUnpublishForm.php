<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_unpublish\Form;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\oe_editorial_unpublish\Event\UnpublishStatesEvent;
use Drupal\workflows\WorkflowTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a form for unpublishing a node.
 */
class NodeUnpublishForm extends ConfirmFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * The node being unpublished.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity repository service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, ModerationInformationInterface $moderation_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->moderationInfo = $moderation_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'node_unpublish_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL): array {
    $this->node = $node;
    $form = parent::buildForm($form, $form_state);
    $workflow = $this->moderationInfo->getWorkflowForEntity($node);
    $unpublished_states = $this->getUnpublishableStates($workflow->getTypePlugin(), $node);
    $unpublished_states = array_map(function ($state) {
      return $state->label();
    }, $unpublished_states);

    $form['unpublish_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the state to unpublish this node'),
      '#options' => $unpublished_states,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->node->moderation_state->value = $form_state->getValue('unpublish_state');
    $this->node->save();
    $this->messenger()->addStatus($this->t('The node %label has been unpublished.', [
      '%label' => $this->node->label(),
    ]));
    $form_state->setRedirectUrl($this->node->toUrl());

  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account accessing the route.
   * @param \Drupal\node\NodeInterface $node
   *   The node being accessed.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, NodeInterface $node): AccessResultInterface {

    if (!$this->moderationInfo->isModeratedEntity($node)) {
      // If the content is not using the corporate workflow, we deny access.
      return AccessResult::forbidden($this->t('Content does not have content moderation enabled.'))->addCacheableDependency($node);
    }

    $storage = $this->entityTypeManager->getStorage($node->getEntityTypeId());
    $latest_revision_id = $storage->getLatestTranslationAffectedRevisionId($node->id(), $node->language()->getId());
    if ($latest_revision_id === NULL || $this->moderationInfo->hasPendingRevision($node) || !$this->moderationInfo->isDefaultRevisionPublished($node)) {
      // If the content's latest revision is not published we deny the access.
      return AccessResult::forbidden($this->t('The last revision of the content is not published.'))->addCacheableDependency($node);
    }

    // Check if the user has a permission to transition to an unpublished state.
    $workflow = $this->moderationInfo->getWorkflowForEntity($node);
    $workflow_type = $workflow->getTypePlugin();
    $unpublished_states = $this->getUnpublishableStates($workflow->getTypePlugin(), $node);
    foreach (array_keys($unpublished_states) as $state_id) {
      $transition_id = $workflow_type->getTransitionFromStateToState($node->moderation_state->value, $state_id);
      if ($account->hasPermission('use oe_corporate_workflow transition ' . $transition_id->id())) {
        return AccessResult::allowed()->addCacheableDependency($node)->addCacheableDependency($workflow)->addCacheableDependency($account);
      }
    }
    return AccessResult::forbidden($this->t('The user does not have a permission to unpublish the node.'))->addCacheableDependency($node)->addCacheableDependency($workflow)->addCacheableDependency($account);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return $this->node->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to unpublish the node %label?', [
      '%label' => $this->node->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Unpublish');
  }

  /**
   * Returns the states that are available to unpublish.
   *
   * @param \Drupal\workflows\WorkflowTypeInterface $worklow_type
   *   The workflow type plugin.
   * @param \Drupal\node\NodeInterface $node
   *   The node that is beind unpublished.
   *
   * @return array
   *   An array of states keyed by the state id.
   */
  protected function getUnpublishableStates(WorkflowTypeInterface $worklow_type, NodeInterface $node): array {
    // Gather a list of unpublishable_states.
    $available_states = $worklow_type->getStates();
    $unpublishable_states = [];
    foreach ($available_states as $state) {
      if (!$state->isPublishedState() && $state->isDefaultRevisionState()) {
        $unpublishable_states[$state->id()] = $state;
      }
    }

    // Gather a list of states to which the node can transition to.
    $current_state = $node->moderation_state->value;
    $available_transitions = $worklow_type->getTransitionsForState($current_state);
    $transitionable_states = [];
    foreach ($available_transitions as $transition) {
      $transitionable_states[$transition->to()->id()] = $transition->to();
    }
    $unpublishable_states = array_intersect_key($unpublishable_states, $transitionable_states);

    // Allow other modules to change the list of unpublishable states.
    $event = new UnpublishStatesEvent($node, $unpublishable_states);
    $this->eventDispatcher->dispatch(UnpublishStatesEvent::EVENT_NAME, $event);
    return $event->getStates();
  }

}
