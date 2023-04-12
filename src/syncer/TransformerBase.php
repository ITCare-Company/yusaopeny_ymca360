<?php

namespace Drupal\yusaopeny_ymca360\syncer;

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
   * Transformer class constructor.
   */
  public function __construct(DataWrapper $data_wrapper, Y360MappingRepository $repository, LoggerChannelInterface $logger) {
    $this->dataWrapper = $data_wrapper;
    $this->repository = $repository;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function transform() {
    $items = $this->dataWrapper->getItems();
    $items_to_update = [];
    // External items ids.
    $external_item_ids = array_keys($items);
    $existing_items = $this->repository->getExistingMappingIds($external_item_ids);
    foreach ($existing_items as $existing_item) {
      if (array_key_exists($existing_item->getY360Id(), $items)) {
        $y360_id = $existing_item->getY360Id();
        $items_to_update[$existing_item->id()] = $items[$y360_id];
        unset($items[$y360_id]);
      }
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
