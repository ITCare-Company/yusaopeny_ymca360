<?php

namespace Drupal\yusaopeny_ymca360_instudio\syncer;

use Drupal\yusaopeny_ymca360\syncer\LoaderBase;
use Drupal\yusaopeny_ymca360\syncer\LoaderInterface;

/**
 * In-studio loader. Pulls its UX config from yusaopeny_ymca360_instudio.settings.
 */
class Loader extends LoaderBase implements LoaderInterface {

  /**
   * {@inheritdoc}
   */
  protected function getSubmoduleConfigName(): string {
    return 'yusaopeny_ymca360_instudio.settings';
  }

}
