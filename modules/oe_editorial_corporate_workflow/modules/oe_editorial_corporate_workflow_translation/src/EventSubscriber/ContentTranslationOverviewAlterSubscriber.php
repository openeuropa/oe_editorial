<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_corporate_workflow_translation\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\oe_translation\Event\ContentTranslationOverviewAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the event used for altering the content translation overview.
 */
class ContentTranslationOverviewAlterSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ContentTranslationOverviewAlterSubscriber instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ContentTranslationOverviewAlterEvent::NAME => 'alterOverview',
    ];
  }

  /**
   * Alters the content translation overview.
   *
   * We change the message presented to the user regarding the translation
   * synchronization in case there is an ongoing request and an update can be
   * made. We include information about the content versions.
   *
   * @param \Drupal\oe_translation\Event\ContentTranslationOverviewAlterEvent $event
   *   The event.
   */
  public function alterOverview(ContentTranslationOverviewAlterEvent $event): void {
    $build = $event->getBuild();
    // If there is no ongoing request, we don't do anything.
    if (!isset($build['#ongoing_languages'])) {
      return;
    }

    $ongoing_languages = $build['#ongoing_languages'];
    if (!$ongoing_languages) {
      return;
    }

    if (!isset($build['provider_info']['info']['message'])) {
      return;
    }

    $info = reset($ongoing_languages);
    /** @var \Drupal\tmgmt\JobItemInterface $job_item */
    $job_item = $this->entityTypeManager->getStorage('tmgmt_job_item')->load($info->tjiid);
    $revision_id = $job_item->get('item_rid')->value;
    $entity_type = $job_item->get('item_type')->value;

    $storage = $this->entityTypeManager->getStorage($entity_type);

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $storage->loadRevision($revision_id);
    $field_definitions = array_filter($entity->getFieldDefinitions(), function (FieldDefinitionInterface $definition) {
      return $definition->getType() === 'entity_version';
    });

    if (!$field_definitions) {
      return;
    }

    // We assume only one version field per entity type.
    $field_name = key($field_definitions);

    $entity = $this->getLatestRevisionForMajor($entity, $field_name);
    $current = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));

    // Display the major version of the entity that the ongoing request
    // originated from.
    $title = $entity->label();
    if ($entity instanceof NodeInterface) {
      $title = $entity->toLink($entity->label(), 'revision', [
        'attributes' => [
          'target' => '_blank',
        ],
      ])->toString();
    }
    $version = implode('.', $entity->get($field_name)->offsetGet(0)->getValue());
    $current_version = implode('.', $current->get($field_name)->offsetGet(0)->getValue());
    $parameters = [
      '@version' => $version,
      '@title' => $title,
      '@current_version' => $current_version,
    ];
    $build['provider_info']['info']['message']['#markup'] = '<br /><br />' . $this->t('Incoming translations from this request will be synchronised with version <em>@version</em> of this content that has the title <em>@title</em>. <br /><br />The current version that can be translated is <em>@current_version</em>.', $parameters);

    $event->setBuild($build);
  }

  /**
   * Returns the latest revision in a given major version.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The version field name.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The revision.
   */
  protected function getLatestRevisionForMajor(ContentEntityInterface $entity, string $field_name): ContentEntityInterface {
    $major = $entity->get($field_name)->get(0)->get('major')->getValue();
    $minor = $entity->get($field_name)->get(0)->get('minor')->getValue();
    $results = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->getQuery()
      ->condition($entity->getEntityType()->getKey('id'), $entity->id())
      ->condition('version.major', $major)
      ->condition('version.minor', $minor)
      ->accessCheck(FALSE)
      ->allRevisions()
      ->execute();

    end($results);
    $vid = key($results);

    return $vid != $entity->getRevisionId() ? $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadRevision($vid) : $entity;
  }

}
