<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_editorial\Traits;

/**
 * Provides methods to handle batch API operations.
 */
trait BatchTrait {

  /**
   * Waits for batches execution to end.
   *
   * First waits for the batch progress bar to appear (confirming the batch
   * page JS has loaded), then waits for the browser to navigate away from the
   * batch URL (confirming the batch completed and redirected).
   *
   * The previous approach of checking for #updateprogress absence was
   * unreliable because in Drupal 11.3+ the JS behavior that creates the
   * progress bar element may not have attached yet, causing the check to
   * return immediately before the batch starts.
   */
  protected function waitForBatchExecution(): void {
    $this->assertSession()->waitForElement('css', '.progress__bar');
    $this->getSession()->wait(180000, "!window.location.pathname.includes('/batch')");
  }

}
