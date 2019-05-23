<?php

declare(strict_types = 1);

namespace Drupal\oe_editorial_corporate_workflow\Services;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an interface for shortcut handler service.
 */
interface ShortcutTransitionHandlerInterface {

  /**
   * Method to handle revisions when shortcuts are used on the moderation form.
   *
   * We need to make sure that intermediary states and revisions are saved so
   * that related events are executed at every step.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function handleShortcutTransitions(array $form, FormStateInterface $form_state);

}
