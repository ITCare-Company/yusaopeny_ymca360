<?php

namespace Drupal\yusaopeny_ymca360;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Provides a view controller for a daxko groupex mapping entity type.
 */
class Y360MappingViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getBuildDefaults(EntityInterface $entity, $view_mode) {
    $build = parent::getBuildDefaults($entity, $view_mode);
    unset($build['#theme']);
    return $build;
  }

}
