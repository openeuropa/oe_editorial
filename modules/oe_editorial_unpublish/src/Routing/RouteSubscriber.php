<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_unpublish\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\oe_editorial_unpublish\Form\ContentEntityUnpublishForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds unpublish routes for all the relevant content entity types.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an instance of RouteSubscriber.
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
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $definitions = $this->entityTypeManager->getDefinitions();
    if (!$definitions) {
      return;
    }

    foreach ($definitions as $definition) {
      // We are only interested in the entity types that have the form class
      // to unpublish content set on it.
      if (!$definition->getFormClass('unpublish')) {
        continue;
      }

      $canonical_route = $collection->get('entity.' . $definition->id() . '.canonical');
      if (!$canonical_route) {
        // It's possible that an entity type doesn't have a canonical route.
        continue;
      }

      $route = new Route(
        // Path.
        $canonical_route->getPath() . '/unpublish',
        // Defaults.
        [
          '_entity_form' => "{$definition->id()}.unpublish",
          '_title' => 'Unpublish',
        ],
        // Requirements.
        [
          '_custom_access' => ContentEntityUnpublishForm::class . '::access',
        ],
        // Options.
        [
          '_admin_route' => TRUE,
          'parameters' => [
            $definition->id() => [
              'type' => 'entity:' . $definition->id(),
            ],
          ],
        ]
      );

      $collection->add('entity.' . $definition->id() . '.unpublish', $route);
    }
  }

}
