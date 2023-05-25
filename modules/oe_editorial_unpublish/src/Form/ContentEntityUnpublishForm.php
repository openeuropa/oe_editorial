<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_unpublish\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
class ContentEntityUnpublishForm extends ContentEntityConfirmFormBase {

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
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
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
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, ModerationInformationInterface $moderation_info, RouteMatchInterface $current_route_match, AccountProxyInterface $current_user) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

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
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
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
  public function getCancelUrl(): Url {
    return $this->entity->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to unpublish %label?', [
      '%label' => $this->getUnpublishingRevision()->label(),
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
    $form = parent::buildForm($form, $form_state);

    $workflow = $this->moderationInfo->getWorkflowForEntity($this->entity);
    $unpublished_states = $this->getUnpublishingStates($workflow, $this->entity);
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
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($this->entity->getEntityTypeId());
    // Keep track of the absolute latest revision ID.
    $latest_revision_id = $storage->getLatestRevisionId($this->entity->id());
    // Retrieve the default revision. This will be published because if the
    // default revision is not published, we don't reach this point, i.e. there
    // is nothing to unpublish. Then unpublish it. This will create a new
    // revision of the entity.
    $default_revision = $this->getUnpublishingRevision();
    $default_revision_id = $default_revision->getRevisionId();
    $default_revision->moderation_state->value = $form_state->getValue('unpublish_state');
    if ($latest_revision_id !== (int) $default_revision_id) {
      // If we have forward drafts, tell the entity version module to use the
      // current (published) revision when determining the moderation state so
      // that the resulting transition which gives the version bump is the
      // correct one: published to "unpublished". Otherwise, it would be draft
      // to "unpublished" which is different than it would be if there were no
      // forward drafts.
      $default_revision->entity_version_use_current_revision = TRUE;
    }
    $default_revision->save();

    // If the absolute last revision ID of the entity was not the default one,
    // i.e. there were draft revisions following, we need to take the last one
    // of these and "revert" them. This means play them forward after the one
    // we just unpublished. This ensures editors can still continue with their
    // new version of the content after the published one was unpublished.
    if ($latest_revision_id !== (int) $default_revision_id) {
      $latest_revision = $storage->loadRevision($latest_revision_id);
      // In case the site is using entity version, we don't wanna affect the
      // version with this operation. It should continue from where it left
      // off.
      $latest_revision->entity_version_no_update = TRUE;
      $latest_revision->setNewRevision();
      $latest_revision->save();
    }
    $this->messenger()->addStatus($this->t('The content %label has been unpublished.', [
      '%label' => $default_revision->label(),
    ]));
    $form_state->setRedirectUrl($this->entity->toUrl());
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
    $entity_form = $routeMatch->getRouteObject()->getDefault('_entity_form');
    [$entity_type_id, $operation] = explode('.', $entity_form);
    $entity = $this->getEntityFromRouteMatch($routeMatch, $entity_type_id);
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['url']);

    if (!$entity) {
      return AccessResult::forbidden('No entity found in the route.')->addCacheableDependency($cache);
    }

    $cache->addCacheContexts(['user.permissions']);
    $cache->addCacheableDependency($entity);

    if (!$this->moderationInfo->isModeratedEntity($entity)) {
      // If the content is not using content moderation, we deny access.
      return AccessResult::forbidden('Content does not have content moderation enabled.')->addCacheableDependency($cache);
    }

    if (!$this->moderationInfo->isDefaultRevisionPublished($entity)) {
      // If the content's default revision is not published we deny the access.
      return AccessResult::forbidden('The default revision of the content is not published.')->addCacheableDependency($cache);
    }

    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
    $cache->addCacheableDependency($workflow);

    $unpublished_states = $this->getUnpublishingStates($workflow, $entity, $account);
    if (empty($unpublished_states)) {
      return AccessResult::forbidden('There are no available states to use for unpublishing the content.')->addCacheableDependency($cache);
    }

    return AccessResult::allowed()->addCacheableDependency($cache);
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
  protected function getUnpublishingStates(WorkflowInterface $workflow, ContentEntityInterface $entity, AccountInterface $account = NULL): array {
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

    // Gather a list of states to which the entity can transition to. For this
    // we need to load the latest default revision and see if a transition
    // can be made from that.
    $default_revision = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
    $current_state = $default_revision->moderation_state->value;
    $available_transitions = $workflow_type->getTransitionsForState($current_state);
    $transitionable_states = [];
    foreach ($available_transitions as $transition) {
      $transitionable_states[$transition->to()->id()] = $transition->to();
    }
    $unpublishable_states = array_intersect_key($unpublishable_states, $transitionable_states);

    // Check if the user has permission to transition to an unpublishing state.
    foreach (array_keys($unpublishable_states) as $state_id) {
      $transition_id = $workflow_type->getTransitionFromStateToState($current_state, $state_id);
      if (!$account->hasPermission('use ' . $workflow->id() . ' transition ' . $transition_id->id())) {
        unset($unpublishable_states[$state_id]);
      }
    }

    // Allow other modules to change the list of unpublishable states.
    $event = new UnpublishStatesEvent($entity, $unpublishable_states);
    $this->eventDispatcher->dispatch($event, UnpublishStatesEvent::EVENT_NAME);
    $unpublishable_states = $event->getStates();

    return $unpublishable_states;
  }

  /**
   * Loads the revision of the entity that will get unpublished.
   *
   * This is the default one and it's expected one exists, otherwise the access
   * callback will prevent this form from loading.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The revision.
   */
  protected function getUnpublishingRevision(): ContentEntityInterface {
    $storage = $this->entityTypeManager->getStorage($this->entity->getEntityTypeId());
    $revision_id = $this->moderationInfo->getDefaultRevisionId($this->entity->getEntityTypeId(), $this->entity->id());
    return $storage->loadRevision($revision_id);
  }

}
