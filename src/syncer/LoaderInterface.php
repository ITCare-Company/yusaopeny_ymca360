<?php

namespace Drupal\yusaopeny_ymca360\syncer;

/**
 * Provides a Loader step interface used by OpenY Syncer.
 */
interface LoaderInterface {

  /**
   * Loads extracted data into storage.
   */
  public function load();

}
