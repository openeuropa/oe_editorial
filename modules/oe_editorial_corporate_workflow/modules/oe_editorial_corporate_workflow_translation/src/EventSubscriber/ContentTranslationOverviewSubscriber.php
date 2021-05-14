<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_corporate_workflow_translation\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_translation\Event\ContentTranslationOverviewAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the content translation overview alter event.
 */
class ContentTranslationOverviewSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * ContentTranslationOverviewSubscriber constructor.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The moderation information service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(ModerationInformationInterface $moderationInformation, LanguageManagerInterface $languageManager) {
    $this->moderationInformation = $moderationInformation;
    $this->languageManager = $languageManager;
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
   * By default, core loads for each language the latest translation affected
   * revision if the entity and links that in the table. However, with our
   * corporate workflow, we can remove translations from the latest version
   * and we don't want this table showing translations from previous versions.
   *
   * @param \Drupal\oe_translation\Event\ContentTranslationOverviewAlterEvent $event
   *   The event.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function alterOverview(ContentTranslationOverviewAlterEvent $event): void {
    $build = $event->getBuild();

    $route_match = $event->getRouteMatch();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $route_match->getParameter($event->getEntityTypeId());

    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      return;
    }

    $entity_languages = $entity->getTranslationLanguages(FALSE);
    $entity_default_language = $entity->getUntranslated()->language();

    $rows = isset($build['languages']) ? $build['languages']['#options'] : $build['content_translation_overview']['#rows'];
    foreach ($rows as &$row) {
      $language_code = is_array($row[0]) && isset($row[0]['hreflang']) ? $row[0]['hreflang'] : NULL;
      if (!$language_code) {
        continue;
      }

      if ($language_code === $entity_default_language->getId()) {
        // We don't want to mess with the default language link.
        continue;
      }

      $row[1] = isset($entity_languages[$language_code]) ? $entity->getTranslation($language_code)->toLink()->toString() : $this->t('n/a');
    }

    if (isset($build['languages'])) {
      $build['languages']['#options'] = $rows;
    }
    else {
      $build['content_translation_overview']['#rows'] = $rows;
    }

    $event->setBuild($build);
  }

}
