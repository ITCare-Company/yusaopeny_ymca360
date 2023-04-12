<?php

namespace Drupal\yusaopeny_ymca360\syncer;

/**
 * Provides an Extractor step interface used by OpenY Syncer.
 */
interface DataWrapperInterface {

  /**
   * Get extracted data items.
   *
   * @return array
   *   Array with items that were extracted.
   */
  public function getItems(): array;

  /**
   * Get transformed data items to be saved into storage.
   *
   * @return array
   *   Array with items to be created.
   */
  public function getItemsToCreate(): array;

  /**
   * Get transformed data items to be updated in the storage.
   *
   * @return array
   *   Array with items to be updated.
   */
  public function getItemsToUpdate(): array;

  /**
   * Get transformed items to be deleted from the storage.
   *
   * @return array
   *   Array with items to be deleted.
   */
  public function getItemsToDelete(): array;

  /**
   * Setter method for $items property.
   *
   * @param array $items
   *   Array with items that were extracted.
   */
  public function setItems(array $items): void;

  /**
   * Setter method for $itemsToCreate property.
   *
   * @param array $items
   *   Array with items to be created.
   */
  public function setItemsToCreate(array $items): void;

  /**
   * Setter method for $itemsToUpdate property.
   *
   * @param array $items
   *   Array with items to be updated.
   */
  public function setItemsToUpdate(array $items): void;

  /**
   * Setter method for $itemsToDelete property.
   *
   * @param array $items
   *   Array with items to be deleted.
   */
  public function setItemsToDelete(array $items): void;

}
