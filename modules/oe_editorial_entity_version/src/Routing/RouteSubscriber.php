<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_entity_version\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\oe_editorial_entity_version\Form\NodeRevisionRevertForm;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters the node revision revert confirm route.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('node.revision_revert_confirm')) {
      $route->setDefault('_form', NodeRevisionRevertForm::class);
      $route->setRequirement('_permission', 'restore version');
    }
  }

}
