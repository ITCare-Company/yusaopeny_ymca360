<?php

namespace Drupal\yusaopeny_ymca360\syncer;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\yusaopeny_ymca360\Y360MappingRepository;

/**
 * Transforms and prepares data received from YMCA360 API.
 *
 * @package Drupal\yusaopeny_ymca360.
 */
abstract class TransformerBase implements TransformerInterface {

  /**
   * DataWrapper.
   *
   * @var \Drupal\yusaopeny_ymca360\syncer\DataWrapper
   */
  protected DataWrapper $dataWrapper;

  /**
   * YMCA360 Mapping Repository.
   *
   * @var \Drupal\yusaopeny_ymca360\Y360MappingRepository
   */
  protected Y360MappingRepository $repository;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The memory cache.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  protected MemoryCacheInterface $memoryCache;

  /**
   * Transformer class constructor.
   */
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
    $items_to_update = [];
    // External items ids.
    $ids = array_keys($items);
    foreach (array_chunk($ids, 100) as $chunk) {
      $mappings = $this->repository->getExistingMappingIds($chunk);
      foreach ($mappings as $mapping) {
        $id = $mapping->getY360Id();
        if (md5(serialize($items[$id])) != $mapping->getHash()) {
          $items_to_update[$mapping->id()] = $items[$id];
        }
        unset($items[$id]);
      }
      $this->memoryCache->deleteAll();
    }
    $this->dataWrapper->setItemsToCreate($items);
    $this->dataWrapper->setItemsToUpdate($items_to_update);
    $this->dataWrapper->setItemsToDelete($this->getItemsToDelete());
  }

  /**
   * Gets items to be deleted from the repository.
   *
   * @return array
   *   Items to be deleted.
   */
  protected function getItemsToDelete() {
    return [];
  }

}
