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
  protected function getActivityCategory() {
    return $this->state->get('yusaopeny_ymca360_livestreams.program_subcategory', self::DEFAULT_ACTIVITY_CATEGORY);
  }

  /**
   * {@inheritDoc}
   */
  protected function getActivity(string $activity_name): int {
    return parent::getActivity($activity_name);
  }

}
