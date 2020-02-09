<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_unpublish\Form;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\oe_editorial_unpublish\Event\UnpublishStatesEvent;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a form for unpublishing a moderated content entity.
 */
class ContentEntityUnpublishForm extends ConfirmFormBase {

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
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity being unpublished.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity repository service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, ModerationInformationInterface $moderation_info, RouteMatchInterface $current_route_match, AccountProxyInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->moderationInfo = $moderation_info;
    $this->currentRouteMatch = $current_route_match;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('content_moderation.moderation_information'),
      $container->get('current_route_match'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_unpublish_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return $this->entity->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to unpublish %label?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Unpublish');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Set the entity on the object so it can be used by parent methods.
    $this->entity = $this->getEntity();
    $form = parent::buildForm($form, $form_state);

    // Set the entity in the form state so we can use it in the submit handler.
    $form_state->set('entity', $this->entity);

    $workflow = $this->moderationInfo->getWorkflowForEntity($this->entity);
    $unpublished_states = $this->getUnpublishableStates($workflow, $this->entity);
    $unpublished_states = array_map(function (StateInterface $state) {
      return $state->label();
    }, $unpublished_states);

    $form['unpublish_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the unpublishing state'),
      '#description' => $this->t('These are the states that mark the content as unpublished.'),
      '#options' => $unpublished_states,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->get('entity');
    $entity->moderation_state->value = $form_state->getValue('unpublish_state');
    $entity->save();
    $this->messenger()->addStatus($this->t('The content %label has been unpublished.', [
      '%label' => $entity->label(),
    ]));
    $form_state->setRedirectUrl($entity->toUrl());
  }

  /**
   * Checks access for to the Unpublish route for a given entity.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account accessing the route.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The actual route match of the route. This can be different than the
   *   current one.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch): AccessResultInterface {
    $entity = $this->getEntity($routeMatch);
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['url']);
    $cache->addCacheContexts(['user.permissions']);
    if (!$entity) {
      return AccessResult::forbidden('No entity found in the route.')->addCacheableDependency($cache);
    }

    $cache->addCacheableDependency($entity);

    if (!$this->moderationInfo->isModeratedEntity($entity)) {
      // If the content is not using content moderation, we deny access.
      return AccessResult::forbidden('Content does not have content moderation enabled.')->addCacheableDependency($cache);
    }

    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $latest_revision_id = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $entity->language()->getId());
    if ($latest_revision_id === NULL || $this->moderationInfo->hasPendingRevision($entity) || !$this->moderationInfo->isDefaultRevisionPublished($entity)) {
      // If the content's latest revision is not published we deny the access.
      return AccessResult::forbidden('The last revision of the content is not published.')->addCacheableDependency($cache);
    }

    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
    $cache->addCacheableDependency($workflow);

    $unpublished_states = $this->getUnpublishableStates($workflow, $entity, $account);
    if (empty($unpublished_states)) {
      return AccessResult::forbidden('There are no available states to use for unpublishing the content.')->addCacheableDependency($cache);
    }

    return AccessResult::allowed()->addCacheableDependency($cache);
  }

  /**
   * Returns the entity being unpublished.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface|null $route_match
   *   The route match where to determine the entity from.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  protected function getEntity(RouteMatchInterface $route_match = NULL): ?ContentEntityInterface {
    $route_match = $route_match ?? $this->currentRouteMatch;
    $route = $route_match->getRouteObject();
    if (!$route->hasOption('_entity_type')) {
      return NULL;
    }

    $entity_type = $route->getOption('_entity_type');
    return $route_match->getParameter($entity_type);
  }

  /**
   * Returns the states that unpublish content.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow type plugin.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity that is being unpublished.
   * @param \Drupal\core\Session\AccountInterface $account
   *   A user account to check permissions for, defaults to the current user.
   *
   * @return array
   *   An array of states keyed by the state id.
   */
  protected function getUnpublishableStates(WorkflowInterface $workflow, ContentEntityInterface $entity, AccountInterface $account = NULL): array {
    $account = $account ?? $this->currentUser;
    $workflow_type = $workflow->getTypePlugin();

    // Gather a list of unpublishable_states.
    $available_states = $workflow_type->getStates();
    $unpublishable_states = [];
    foreach ($available_states as $state) {
      if (!$state->isPublishedState() && $state->isDefaultRevisionState()) {
        $unpublishable_states[$state->id()] = $state;
      }
    }

    // Gather a list of states to which the entity can transition to.
    $current_state = $entity->moderation_state->value;
    $available_transitions = $workflow_type->getTransitionsForState($current_state);
    $transitionable_states = [];
    foreach ($available_transitions as $transition) {
      $transitionable_states[$transition->to()->id()] = $transition->to();
    }
    $unpublishable_states = array_intersect_key($unpublishable_states, $transitionable_states);

    // Check if the user has permission to transition to an unpublishing state.
    foreach (array_keys($unpublishable_states) as $state_id) {
      $transition_id = $workflow_type->getTransitionFromStateToState($entity->moderation_state->value, $state_id);
      if (!$account->hasPermission('use ' . $workflow->id() . ' transition ' . $transition_id->id())) {
        unset($unpublishable_states[$state_id]);
      }
    }

    // Allow other modules to change the list of unpublishable states.
    $event = new UnpublishStatesEvent($entity, $unpublishable_states);
    $this->eventDispatcher->dispatch(UnpublishStatesEvent::EVENT_NAME, $event);
    $unpublishable_states = $event->getStates();

    return $unpublishable_states;
  }

}
