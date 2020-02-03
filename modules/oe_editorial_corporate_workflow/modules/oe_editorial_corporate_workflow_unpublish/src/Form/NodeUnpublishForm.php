<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_corporate_workflow_unpublish\Form;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\workflows\WorkflowTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for unpublishing a node.
 */
class NodeUnpublishForm extends ConfirmFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $entityTypeManager;

  /**
   * The moderation information service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
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
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModerationInformationInterface $moderation_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationInfo = $moderation_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_unpublish_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $this->node = $node;
    $form = parent::buildForm($form, $form_state);
    $workflow = $this->moderationInfo->getWorkflowForEntity($node);
    $unpublished_states = $this->getWorkflowUnpublishedStates($workflow->getTypePlugin());

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
  public function submitForm(array &$form, FormStateInterface $form_state) {
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
  public function access(AccountInterface $account, NodeInterface $node) {

    $workflow = $this->moderationInfo->getWorkflowForEntity($node);
    $workflow_type = $workflow->getTypePlugin();
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      // If the content is not using the corporate workflow, we deny access.
      return AccessResult::forbidden($this->t('Content does not use the OpenEuropa corporate workflow.'))->addCacheableDependency($node)->addCacheableDependency($workflow);
    }

    $storage = $this->entityTypeManager->getStorage($node->getEntityTypeId());
    $latest_revision_id = $storage->getLatestTranslationAffectedRevisionId($node->id(), $node->language()->getId());
    if ($latest_revision_id === NULL || $this->moderationInfo->hasPendingRevision($node) || !$this->moderationInfo->isDefaultRevisionPublished($node)) {
      // If the content's latest revision is not published we deny the access.
      return AccessResult::forbidden($this->t('The last revision of the content is not published.'))->addCacheableDependency($node)->addCacheableDependency($workflow);
    }

    // Check if the user has a permission to transition to an unpublished state.
    $unpublished_states = $this->getWorkflowUnpublishedStates($workflow->getTypePlugin());
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
  public function getCancelUrl() {
    return $this->node->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to unpublish the node %label?', [
      '%label' => $this->node->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Unpublish');
  }

  /**
   * Returns the states from a workflow type that are available to unpublish.
   *
   * @param \Drupal\workflows\WorkflowTypeInterface $type
   *   The workflow type.
   *
   * @return array
   *   An array of states keyed by the state id.
   */
  protected function getWorkflowUnpublishedStates(WorkflowTypeInterface $type) {
    $available_states = $type->getStates();
    $unpublished_states = [];
    foreach ($available_states as $state) {
      if (!$state->isPublishedState() && $state->isDefaultRevisionState()) {
        $unpublished_states[$state->id()] = $state->label();
      }
    }
    return $unpublished_states;
  }

}
