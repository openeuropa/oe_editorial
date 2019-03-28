<?php

namespace Drupal\oe_editorial_corporate_workflow;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service provider for the Group module.
 *
 * This is used to alter the content moderation services for integration with
 * the group module. This can't be done via a normal service declaration as
 * decorating optional services is not supported.
 */
class OeEditorialCorporateWorkflowServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['content_moderation'])) {
      // Decorate the state transition validation service.
      $state_transition_definition = new Definition(StateTransitionValidation::class, [
        new Reference('oe_editorial_corporate_workflow.state_transition_validation.inner'),
        new Reference('content_moderation.moderation_information'),
        new Reference('current_route_match'),
        new Reference('entity_type.manager'),
      ]);
      $state_transition_definition->setPublic(TRUE);
      $state_transition_definition->setDecoratedService('content_moderation.state_transition_validation');
      $container->setDefinition('oe_editorial_corporate_workflow.state_transition_validation', $state_transition_definition);
    }
  }

}
