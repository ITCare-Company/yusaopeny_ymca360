<?php

namespace Drupal\yusaopeny_ymca360_livestreams\syncer;

use Drupal\yusaopeny_ymca360\syncer\LoaderBase;
use Drupal\yusaopeny_ymca360\syncer\LoaderInterface;

/**
 * {@inheritDoc}
 */
class Loader extends LoaderBase implements LoaderInterface {

  /**
   * {@inheritDoc}
   */
  protected function getActivityCategory(int $schedule_id): int {
    return (int) $this->state->get('yusaopeny_ymca360_livestreams.program_subcategory', 0);
  }

}
