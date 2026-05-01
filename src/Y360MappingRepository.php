<?php

namespace Drupal\yusaopeny_ymca360;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * YMCA360 Mapping Repository class.
 */
class Y360MappingRepository {

  const STORAGE = 'y360_mapping';

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public EntityTypeManagerInterface $entityTypeManager;

  /**
   * Entity Storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  public EntityStorageInterface $storage;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  public LoggerChannelInterface $logger;

  /**
   * Y360MappingRepository constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $connection, LoggerChannelInterface $logger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->storage = $this->entityTypeManager->getStorage(self::STORAGE);
    $this->connection = $connection;
    $this->logger = $logger;
  }

  /**
   * Returns Y360Mapping storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   Instance of the storage class.
   */
  public function getStorage() {
    return $this->storage;
  }

  /**
   * Gets all YMCA360 Mapping entity IDs form the storage.
   *
   * @return array
   *   Array of existing mappings ids.
   */
  public function getExistingMappingIds($y360_ids) {
    if (empty($y360_ids)) {
      return [];
    }
    return $this->getStorage()->loadByProperties(['y360id' => $y360_ids]);
  }

  /**
   * Creates Y360Mapping Entity.
   *
   * @param array $data
   *   Array with item data.
   * @param \Drupal\node\NodeInterface|int $session
   *   Session Node instance.
   * @param \Drupal\node\NodeInterface|int $location
   *   Location ID.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function create(array $data, $session, $location): void {
    /**
     * @var \Drupal\yusaopeny_ymca360\Entity\Y360Mapping $mapping
     */
    $mapping = $this->storage->create([
      'y360id' => $data['id'],
      'start_at' => $this->formatIsoDate($data['start_at']),
      'hash' => md5(serialize($data)),
      'source_data' => $data,
      'session' => $session,
      'location' => $location,
    ]);

    $mapping->save();
  }

  /**
   * Updates mapping entity in the storage.
   *
   * @param array $data
   *   Array with item data.
   * @param \Drupal\node\NodeInterface|int $session
   *   Session Node instance.
   * @param \Drupal\node\NodeInterface|int $location
   *   Location ID.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function update(array $data, $session, $location): void {
    $items = $this->storage->loadByProperties(['y360id' => $data['id']]);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $item */
    $item = reset($items);
    $item->set('y360id', $data['id']);
    $item->set('start_at', $this->formatIsoDate($data['start_at']));
    $item->set('hash', md5(serialize($data)));
    $item->set('source_data', $data);

    if ($session) {
      $item->set('session', $session);
    }

    if ($location) {
      $item->set('location', $location);
    }

    $item->save();
  }

  /**
   * Deletes mapping entity from the storage.
   *
   * @param int $mapping_id
   *   Y360Mapping ID to be deleted.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete(int $mapping_id): void {
    // Mapping may already be gone due to cascade from the session delete
    // (hook_ENTITY_TYPE_delete in yusaopeny_ymca360.module removes mappings
    // pointing at the deleted session). Tolerate that.
    $item = $this->storage->load($mapping_id);
    if ($item !== NULL) {
      $item->delete();
    }
  }

  /**
   * Formats date in ISO format to the storage format.
   *
   * @param string $iso_date
   *   Date string.
   *
   * @return string|null
   *   Date string in data storage format.
   */
  public function formatIsoDate(string $iso_date): ?string {
    // Storage format is naive UTC (DATETIME_STORAGE_TIMEZONE = UTC), so we
    // must convert before formatting — otherwise an API string without an
    // offset would be persisted in the server's local timezone and
    // y360:status / window queries would silently miscount.
    $date = new DateTimePlus($iso_date);
    $date->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    return $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
  }

  /**
   * Loads every mapping in storage, keyed by mapping id → y360 id.
   *
   * The syncer treats extraction as the source of truth, so reconciliation
   * needs to see every stored mapping, not just those inside a window.
   *
   * @return array<int, int>
   *   Array keyed by mapping ID, values are the external y360_id.
   */
  public function getAllMappings(): array {
    $rows = $this->connection->select(self::STORAGE, 'm')
      ->fields('m', ['id', 'y360id'])
      ->execute()
      ->fetchAllKeyed();
    $map = [];
    foreach ($rows as $id => $y360id) {
      $map[(int) $id] = (int) $y360id;
    }
    return $map;
  }

  /**
   * Resets hashes for all mapping entities.
   *
   * Effectively enforces data updates.
   */
  public function resetHashes(): void {
    $this->connection
      ->update(self::STORAGE)
      ->fields(['hash' => ''])
      ->execute();
    // Raw UPDATE bypasses persistent caches — invalidate the entity-list
    // cache tag so any loaded mapping entity (in this request or a CDN
    // render) does not keep the stale hash.
    $this->storage->resetCache();
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['y360_mapping_list']);
  }

}
