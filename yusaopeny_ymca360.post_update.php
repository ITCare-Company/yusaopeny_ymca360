<?php

/**
 * @file
 * YUSA OpenY YMCA360 integration content migrations.
 */

/**
 * Resets hashes to force data sync.
 */
function yusaopeny_ymca360_post_update_reset_hashes_001() {
  \Drupal::service('yusaopeny_ymca360.mapping_repository')->resetHashes();
}
