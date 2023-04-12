<?php

/**
 * @file
 * Hooks provided by the YUSA OpenY YMCA360 integration module.
 */

use Drupal\node\NodeInterface;

/**
 * Modify the session node to be updated or created during YMCA360 sync.
 *
 * @param \Drupal\node\NodeInterface $session
 *   Session to be created or updated.
 * @param array $data
 *   The data from YMCA360.
 */
function hook_yusaopeny_ymca360_session_alter(NodeInterface $session, array $data) {
  $session->setPublished();
  $session->setTitle('YMCA360: ' . $data['title']);
}
