<?php

namespace Drupal\yusaopeny_ymca360\syncer;

/**
 * Provides a Transformer step interface used by OpenY Syncer.
 */
interface TransformerInterface {

  /**
   * Transforms extracted data into objects suitable for loading into storage.
   */
  public function transform();

}
