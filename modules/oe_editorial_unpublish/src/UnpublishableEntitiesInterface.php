<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_unpublish;

/**
 * Determines the entity types that can be unpublished.
 */
interface UnpublishableEntitiesInterface {

  /**
   * Returns the entity types that can be unpublished.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   The entity type definitions.
   */
  public function getDefinitions(): array;

}
