<?php

namespace Drupal\yusaopeny_ymca360_instudio\syncer;

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
    return $this->state->get('yusaopeny_ymca360_instudio.program_subcategory', self::DEFAULT_ACTIVITY_CATEGORY);
  }

  /**
   * {@inheritDoc}
   */
  protected function getActivity(string $activityName): int {
    // @todo Fix: Hardcoded Activity name for now.
    $activityName = 'InStudio (YMCA360 Virtual Instructor)';
    return parent::getActivity($activityName);
  }

}
