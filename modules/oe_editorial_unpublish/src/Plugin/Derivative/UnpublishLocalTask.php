<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_unpublish\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\oe_editorial_unpublish\UnpublishableEntitiesInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver class for creating local task links for unpublishing content.
 */
class UnpublishLocalTask extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The unpublishable content entities service.
   *
   * @var \Drupal\oe_editorial_unpublish\UnpublishableContentEntities
   */
  protected $unpublishableContentEntities;

  /**
   * UnpublishLocalTask constructor.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\oe_editorial_unpublish\UnpublishableEntitiesInterface $unpublishableContentEntities
   *   The unpublishable content entities service.
   */
  public function __construct(string $base_plugin_id, UnpublishableEntitiesInterface $unpublishableContentEntities) {
    $this->unpublishableContentEntities = $unpublishableContentEntities;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('oe_editorial_unpublish.unpublishable_entities')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $tasks = [];

    $definitions = $this->unpublishableContentEntities->getDefinitions();
    foreach ($definitions as $definition) {
      $tasks[$definition->id()] = [
        'route_name' => 'entity.' . $definition->id() . '.unpublish',
        'base_route' => 'entity.' . $definition->id() . '.canonical',
      ] + $base_plugin_definition;
    }

    return $tasks;
  }

}
