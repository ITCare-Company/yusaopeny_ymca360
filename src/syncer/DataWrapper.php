<?php

namespace Drupal\yusaopeny_ymca360\syncer;

/**
 * DataWrapper class to transport data between syncer steps.
 */
class DataWrapper implements DataWrapperInterface {

  /**
   * Array with extracted items.
   *
   * @var array
   */
  private array $items = [];

  /**
   * Array with items to be created.
   *
   * @var array
   */
  private array $itemsToCreate = [];

  /**
   * Array with items to be updated.
   *
   * @var array
   */
  private array $itemsToUpdate = [];

  /**
   * Array with items to be deleted.
   *
   * @var array
   */
  private array $itemsToDelete = [];

  /**
   * {@inheritDoc}
   */
  public function getItems(): array {
    return $this->items;
  }

  /**
   * {@inheritDoc}
   */
  public function getItemsToCreate(): array {
    return $this->itemsToCreate;
  }

  /**
   * {@inheritDoc}
   */
  public function getItemsToUpdate(): array {
    return $this->itemsToUpdate;
  }

  /**
   * {@inheritDoc}
   */
  public function getItemsToDelete(): array {
    return $this->itemsToDelete;
  }

  /**
   * {@inheritDoc}
   */
  public function setItems(array $items): void {
    $ids = array_column($items, 'id');
    $this->items = array_combine($ids, $items);
  }

  /**
   * {@inheritDoc}
   */
  public function setItemsToCreate(array $items): void {
    $this->itemsToCreate = $items;
  }

  /**
   * {@inheritDoc}
   */
  public function setItemsToUpdate(array $items): void {
    $this->itemsToUpdate = $items;
  }

  /**
   * {@inheritDoc}
   */
  public function setItemsToDelete(array $items): void {
    $this->itemsToDelete = $items;
  }

}
