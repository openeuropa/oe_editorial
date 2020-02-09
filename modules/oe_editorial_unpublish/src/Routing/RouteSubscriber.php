<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_unpublish\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\oe_editorial_unpublish\Form\ContentEntityUnpublishForm;
use Drupal\oe_editorial_unpublish\UnpublishableContentEntities;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds unpublish routes for all the relevant content entity types.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The unpublishable content entities service.
   *
   * @var \Drupal\oe_editorial_unpublish\UnpublishableContentEntities
   */
  protected $unpublishableContentEntities;

  /**
   * RouteSubscriber constructor.
   *
   * @param \Drupal\oe_editorial_unpublish\UnpublishableContentEntities $unpublishableContentEntities
   *   The unpublishable content entities service.
   */
  public function __construct(UnpublishableContentEntities $unpublishableContentEntities) {
    $this->unpublishableContentEntities = $unpublishableContentEntities;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $definitions = $this->unpublishableContentEntities->getUnpublishableDefinitions();
    if (!$definitions) {
      return;
    }

    foreach ($definitions as $definition) {
      $canonical_route = $collection->get('entity.' . $definition->id() . '.canonical');
      $route = new Route(
        // Path.
        $canonical_route->getPath() . '/unpublish',
        // Defaults.
        [
          '_form' => ContentEntityUnpublishForm::class,
          '_title' => 'Unpublish',
        ],
        // Requirements.
        [
          '_custom_access' => ContentEntityUnpublishForm::class . ':access',
        ],
        // Options.
        [
          '_admin_route' => TRUE,
          // Set the entity type so we can determine dynamically the parameter.
          '_entity_type' => $definition->id(),
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
