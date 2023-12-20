<?php

namespace Drupal\yusaopeny_ymca360;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Finds and removes past sessions.
 */
class Y360Cleaner {

  /**
   * Entity Storage for the y360_mapping entity type.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  public EntityStorageInterface $storage;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  public TimeInterface $time;

  /**
   * Y360Cleaner constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, TimeInterface $time) {
    $this->storage = $entityTypeManager->getStorage('y360_mapping');
    $this->time = $time;
  }

  /**
   * Cleans up entities older than a specified time limit.
   *
   * @param int $limit
   *   The number of entities to clean up (default 50).
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function cleanup(int $limit = 50): void {
    $entities = $this->loadEntitiesToDelete($limit);
    /** @var \Drupal\yusaopeny_ymca360\Entity\Y360Mapping $entity */
    foreach ($entities as $entity) {
      $entity->getSession()->delete();
    }
    $this->storage->delete($entities);
  }

  /**
   * Loads entities matching deletion criteria.
   *
   * Loads a batch of mapping entities that started not less than a week ago.
   *
   * @param int $limit
   *   Maximum number of entities to load.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Entities to be deleted.
   */
  public function loadEntitiesToDelete(int $limit): array {
    $time = (new \DateTime())
      ->setTimestamp($this->time->getRequestTime())
      ->setTimezone(new \DateTimeZone('UTC'))
      ->modify('-7 days')
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $ids = $this->storage
      ->getQuery()
      ->condition('start_at', $time, '<')
      ->sort('start_at')
      ->range(0, $limit)
      ->accessCheck(FALSE)
      ->execute();
    return $this->storage->loadMultiple($ids);
  }

}
