<?php

declare(strict_types=1);

namespace Drupal\oe_editorial_entity_version\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a corporate workflow entity revision.
 */
class CorporateWorkflowEntityRevisionRevertForm extends ConfirmFormBase {

  /**
   * The revision.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $revision;

  /**
   * The machine name of the version field of the entity.
   *
   * @var string
   */
  protected $versionField;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new CorporateWorkflowEntityRevisionRevertForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, DateFormatterInterface $date_formatter, TimeInterface $time) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_version_restore_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to restore the @version version from @revision-date?', [
      '@revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
      '@version' => $this->getVersionString($this->revision),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.' . $this->revision->getEntityTypeId() . '.version_history', [$this->revision->getEntityTypeId() => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Restore');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $_entity_revision = NULL) {
    $this->revision = $_entity_revision;
    $this->versionField = $this->getVersionField($this->revision);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type = $this->revision->getEntityTypeId();
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $this->revision->setNewRevision();
    $revision_version_string = $this->getVersionString($this->revision);
    if ($this->revision instanceof RevisionLogInterface) {
      $this->revision->revision_log = $this->t('Version @version has been restored by @user.', [
        '@version' => $revision_version_string,
        '@user' => $this->currentUser()->getDisplayName(),
      ]);
      $this->revision->setRevisionUserId($this->currentUser()->id());
      $this->revision->setRevisionCreationTime($this->time->getRequestTime());
    }

    if ($this->revision instanceof EntityChangedInterface) {
      $this->revision->setChangedTime($this->time->getRequestTime());
      foreach ($this->revision->getTranslationLanguages(FALSE) as $language) {
        $this->revision->getTranslation($language->getId())->setChangedTime($this->time->getRequestTime());
      }
    }

    $this->revision->set('moderation_state', 'draft');

    // Load the latest revision to make sure the version numbers continue
    // from the last version to increase the minor by one.
    $latest_revision_id = $storage->getLatestRevisionId($this->revision->id());
    $latest_revision = $storage->loadRevision($latest_revision_id);
    $latest_revision->get('version')->first()->increase('minor');
    $this->revision->set('version', $latest_revision->get('version')->getValue());

    // In this case, we don't want the version values to update automatically.
    $this->revision->entity_version_no_update = TRUE;
    $this->revision->save();

    $this->messenger()->addStatus($this->t('Version @version has been restored.', [
      '@version' => $revision_version_string,
    ]));
    $form_state->setRedirect('entity.' . $entity_type . '.version_history', [$entity_type => $this->revision->id()]);
  }

  /**
   * Gets the version number from an entity revision.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision
   *   The entity revision.
   *
   * @return string
   *   The version number of the revision as string.
   */
  protected function getVersionString(RevisionableInterface $revision): string {
    $version_number = $revision->get($this->versionField)->getValue();
    $version_number = reset($version_number);

    return implode('.', $version_number);
  }

  /**
   * Gets the machine name of the version field of the entity.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision
   *   The entity revision.
   *
   * @return string
   *   The machine name of the version field of the entity.
   */
  protected function getVersionField(RevisionableInterface $revision): string {
    $version_field = array_filter($this->entityFieldManager->getFieldDefinitions($revision->getEntityTypeId(), $revision->bundle()), function ($field_definition) {
      return $field_definition->getType() === 'entity_version';
    });

    reset($version_field);

    return key($version_field);
  }

}
