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
   * Current sync window (UNIX timestamps, UTC).
   *
   * @var array{from: ?int, to: ?int}
   */
  private array $syncWindow = ['from' => NULL, 'to' => NULL];

  /**
   * Hard cap on deletions per run (0 = unlimited).
   */
  private int $maxDeletesPerRun = 0;

  /**
   * If true, the transformer skips orphan-by-absence reconciliation.
   *
   * Set when the extractor's circuit breaker tripped (consecutive empty
   * extracts) — the API state is unreliable and removing every stored
   * mapping that did not come back would cascade into a wipe.
   */
  private bool $skipOrphanReconciliation = FALSE;

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

  /**
   * Records the sync window covered by the current extract step.
   *
   * @param int $from
   *   UNIX timestamp (UTC) for window start.
   * @param int $to
   *   UNIX timestamp (UTC) for window end.
   */
  public function setSyncWindow(int $from, int $to): void {
    $this->syncWindow = ['from' => $from, 'to' => $to];
  }

  /**
   * Returns the sync window covered by the current extract step.
   *
   * @return array{from: ?int, to: ?int}
   */
  public function getSyncWindow(): array {
    return $this->syncWindow;
  }

  public function setMaxDeletesPerRun(int $maxDeletesPerRun): void {
    $this->maxDeletesPerRun = max(0, $maxDeletesPerRun);
  }

  public function getMaxDeletesPerRun(): int {
    return $this->maxDeletesPerRun;
  }

  public function setSkipOrphanReconciliation(bool $skip): void {
    $this->skipOrphanReconciliation = $skip;
  }

  public function shouldSkipOrphanReconciliation(): bool {
    return $this->skipOrphanReconciliation;
  }

}
