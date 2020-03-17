<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_unpublish\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver class for creating local task links for unpublishing content.
 */
class UnpublishLocalTask extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an instance of RouteSubscriber.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(string $base_plugin_id, EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $tasks = [];

    $definitions = $this->entityTypeManager->getDefinitions();

    foreach ($definitions as $definition) {
      if (!$definition->getFormClass('unpublish')) {
        continue;
      }

      $tasks[$definition->id()] = [
        'route_name' => 'entity.' . $definition->id() . '.unpublish',
        'base_route' => 'entity.' . $definition->id() . '.canonical',
      ] + $base_plugin_definition;
    }

    return $tasks;
  }

}
