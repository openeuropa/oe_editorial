<?php

declare(strict_types=1);

namespace Drupal\oe_editorial_corporate_workflow;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Service provider for OE Editorial Corporate Workflow.
 */
class OeEditorialCorporateWorkflowServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->has('content_moderation.state_transition_validation')) {
      $definition = $container->getDefinition('content_moderation.state_transition_validation');
      // We override the validator for state transition.
      $definition->setClass('Drupal\oe_editorial_corporate_workflow\CorporateWorkflowStateTransitionValidation');
    }
  }

}
