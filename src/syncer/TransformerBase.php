<?php

namespace Drupal\yusaopeny_ymca360\syncer;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\yusaopeny_ymca360\Y360MappingRepository;

/**
 * Transforms and prepares data received from YMCA360 API.
 */
abstract class TransformerBase implements TransformerInterface {

  /**
   * Upstream item statuses that mean the occurrence is gone for good.
   *
   * Only `deleted` removes the mapping outright. `canceled` is kept so the
   * site can still surface "CANCELLED" to members (per ITCR-1239); how
   * it's rendered is controlled by canceled_publish_behavior in settings.
   */
  protected const DELETED_STATUSES = ['deleted'];

  protected DataWrapper $dataWrapper;

  protected Y360MappingRepository $repository;

  protected LoggerChannelInterface $logger;

  protected MemoryCacheInterface $memoryCache;

  /**
   * Mapping IDs earmarked for deletion during transform().
   *
   * @var int[]
   */
  protected array $deletionQueue = [];

  public function __construct(DataWrapper $data_wrapper, Y360MappingRepository $repository, LoggerChannelInterface $logger, MemoryCacheInterface $memory_cache) {
    $this->dataWrapper = $data_wrapper;
    $this->repository = $repository;
    $this->logger = $logger;
    $this->memoryCache = $memory_cache;
  }

  /**
   * {@inheritdoc}
   */
  public function transform() {
    $items = $this->dataWrapper->getItems();
    $this->deletionQueue = [];

    $items = $this->extractDeletedItems($items);
    $items_to_update = $this->classifyAgainstExistingMappings($items);

    $this->dataWrapper->setItemsToCreate($items);
    $this->dataWrapper->setItemsToUpdate($items_to_update);
    $this->dataWrapper->setItemsToDelete($this->buildDeletionList());

    $this->logger->info('[TRANSFORMER] Classified items: create=%c update=%u delete=%d.', [
      '%c' => count($items),
      '%u' => count($items_to_update),
      '%d' => count($this->dataWrapper->getItemsToDelete()),
    ]);
  }

  /**
   * Extracts items whose status marks them as deleted upstream.
   *
   * Deleted items are removed from the working set and their existing
   * mappings (if any) are queued for deletion.
   *
   * @param array $items
   *   Extracted items keyed by y360 id.
   *
   * @return array
   *   Items remaining after pulling out the deleted ones.
   */
  protected function extractDeletedItems(array $items): array {
    $deletedIds = [];
    foreach ($items as $id => $item) {
      if (in_array($item['status'] ?? '', self::DELETED_STATUSES, TRUE)) {
        $deletedIds[] = $id;
      }
    }
    if (empty($deletedIds)) {
      return $items;
    }
    foreach ($this->repository->getExistingMappingIds($deletedIds) as $mapping) {
      $this->deletionQueue[] = (int) $mapping->id();
      unset($items[$mapping->getY360Id()]);
    }
    // Items that were already absent from Drupal — just drop them.
    foreach ($deletedIds as $id) {
      unset($items[$id]);
    }
    return $items;
  }

  /**
   * Splits remaining items into create vs update by comparing to mappings.
   *
   * Items whose hash matches an existing mapping are considered unchanged
   * and are dropped from the working set (they become no-ops).
   *
   * @param array $items
   *   Items remaining after deleted ones have been extracted. Passed by
   *   reference so matched items can be removed in place.
   *
   * @return array
   *   Items to update, keyed by existing mapping ID.
   */
  protected function classifyAgainstExistingMappings(array &$items): array {
    $items_to_update = [];
    foreach (array_chunk(array_keys($items), 100) as $chunk) {
      $mappings = $this->repository->getExistingMappingIds($chunk);
      foreach ($mappings as $mapping) {
        $id = $mapping->getY360Id();
        if (md5(serialize($items[$id])) !== $mapping->getHash()) {
          $items_to_update[$mapping->id()] = $items[$id];
        }
        unset($items[$id]);
      }
      $this->memoryCache->deleteAll();
    }
    return $items_to_update;
  }

  /**
   * Builds the final delete list: status-driven plus safety net.
   *
   * Safety net catches occurrences that silently disappeared from the API
   * (e.g. deleted before we ever saw them as canceled). Anything stored in
   * the current window whose y360 id is not present in the extracted set is
   * considered orphaned.
   *
   * @return int[]
   *   Mapping IDs to delete.
   */
  protected function buildDeletionList(): array {
    $deleteIds = array_merge($this->deletionQueue, $this->findOrphanMappings());
    $deleteIds = array_values(array_unique(array_map('intval', $deleteIds)));
    return $this->enforceDeleteCap($deleteIds);
  }

  /**
   * Applies max-deletes-per-run cap and logs if exceeded.
   */
  protected function enforceDeleteCap(array $deleteIds): array {
    $cap = $this->dataWrapper->getMaxDeletesPerRun();
    if ($cap === 0 || count($deleteIds) <= $cap) {
      return $deleteIds;
    }
    $this->logger->warning('[TRANSFORMER] Delete list (%count) exceeds max_deletes_per_run (%cap). Capping to %cap and skipping the rest this run.', [
      '%count' => count($deleteIds),
      '%cap' => $cap,
    ]);
    return array_slice($deleteIds, 0, $cap);
  }

  /**
   * Mappings present in DB but missing from the extracted set.
   *
   * Sync is the source of truth: anything in Drupal that did not come back
   * from the API (either outside the current window, or in-window with
   * unknown y360 id) is orphaned by definition and gets removed.
   */
  protected function findOrphanMappings(): array {
    $stored = $this->repository->getAllMappings();
    if (empty($stored)) {
      return [];
    }
    $seen = array_flip($this->collectExtractedY360Ids());
    $orphans = [];
    foreach ($stored as $mappingId => $y360Id) {
      if (!isset($seen[$y360Id])) {
        $orphans[] = $mappingId;
      }
    }
    return $orphans;
  }

  /**
   * Returns the set of y360 ids present in the current extract.
   *
   * Reads the original dataWrapper items (before classify mutated the local
   * copy) so every extracted id is considered, including ones already
   * matched to an existing mapping.
   */
  protected function collectExtractedY360Ids(): array {
    return array_keys($this->dataWrapper->getItems());
  }

  /**
   * Subclass hook for injecting extra mapping IDs into the delete list.
   *
   * @return int[]
   *
   * @deprecated Prefer status-based detection + safety net.
   */
  protected function getItemsToDelete() {
    return [];
  }

}
