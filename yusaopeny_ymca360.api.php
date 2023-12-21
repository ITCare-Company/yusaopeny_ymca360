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

/**
 * Modify the class node to be created during YMCA360 sync.
 *
 * @param \Drupal\node\NodeInterface $class
 *   Class to be created or updated.
 * @param array $data
 *   The data from YMCA360.
 */
function hook_yusaopeny_ymca360_class_create_alter(NodeInterface $class, array $data) {
  $class->setPublished();
  $class->setTitle('YMCA360: ' . $data['title']);
}

/**
 * Modify the activity node to be created during YMCA360 sync.
 *
 * @param \Drupal\node\NodeInterface $activity
 *   Activity to be created or updated.
 */
function hook_yusaopeny_ymca360_activity_create_alter(NodeInterface $activity) {
  $activity->setPublished();
  $activity->setTitle('YMCA360: ' . $activity->label());
}