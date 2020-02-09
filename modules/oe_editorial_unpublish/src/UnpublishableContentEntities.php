<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_unpublish;

use Drupal\content_moderation\ModerationInformation;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Determines the content entity types that can be unpublished.
 */
class UnpublishableContentEntities {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The content moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformation
   */
  protected $moderationInformation;

  /**
   * UnpublishableContentEntities constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\content_moderation\ModerationInformation $moderationInformation
   *   The content moderation information service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModerationInformation $moderationInformation) {
    $this->entityTypeManager = $entityTypeManager;
    $this->moderationInformation = $moderationInformation;
  }

  /**
   * Returns the content entity types that can be unpublished.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface[]
   *   The content entity type definitions.
   */
  public function getUnpublishableDefinitions(): array {
    $definitions = $this->entityTypeManager->getDefinitions();
    $unpublishable = [];

    foreach ($definitions as $definition) {
      if (!$definition instanceof ContentEntityTypeInterface) {
        // We are only interested in the content entity types.
        continue;
      }

      if (!$definition->hasKey('published')) {
        // We are only interested in the content entity types that can be
        // published (editorial base).
        continue;
      }

      if (!$this->moderationInformation->isModeratedEntityType($definition)) {
        // We are only interested if the content is moderated.
        continue;
      }

      if (!$definition->hasLinkTemplate('canonical')) {
        // We are only interested in entity types that have canonical links.
        continue;
      }

      $unpublishable[$definition->id()] = $definition;
    }

    return $unpublishable;
  }

}
