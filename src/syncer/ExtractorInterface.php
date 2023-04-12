<?php

namespace Drupal\yusaopeny_ymca360\syncer;

/**
 * Provides an Extractor step interface used by OpenY Syncer.
 */
interface ExtractorInterface {

  /**
   * Extracts the data from remote source.
   */
  public function extract();

}
