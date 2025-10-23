<?php

declare(strict_types=1);

namespace Drupal\oe_editorial_entity_version\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\oe_editorial_entity_version\Form\CorporateWorkflowEntityRevisionRevertForm;
use Drupal\workflows\Entity\Workflow;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters the revision revert confirm route.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = Workflow::load('oe_corporate_workflow');
    if (!$workflow) {
      return;
    }
    $entity_types = $workflow->get('type_settings')['entity_types'];
    foreach (array_keys($entity_types) as $entity_type) {
      $route_name = $entity_type === 'node' ? 'node.revision_revert_confirm' : 'entity.' . $entity_type . '.revision_revert_form';
      if ($route = $collection->get($route_name)) {
        $route->setDefault('_form', CorporateWorkflowEntityRevisionRevertForm::class);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -100];
    return $events;
  }

}
