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
   * Batches are considered executed when the update progress bar is not present
   * on the page anymore.
   */
  protected function waitForBatchExecution(): void {
    $this->getSession()->wait(180000, 'document.querySelector("#updateprogress") === null');
  }

}
