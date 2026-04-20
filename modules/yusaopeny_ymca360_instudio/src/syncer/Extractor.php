<?php

namespace Drupal\yusaopeny_ymca360_instudio\syncer;

use Drupal\yusaopeny_ymca360\syncer\ExtractorBase;
use Drupal\yusaopeny_ymca360\syncer\ExtractorInterface;

/**
 * Fetches in-studio session data from the YMCA360 API.
 *
 * Uses a time-window fetch so we never exceed the API's result cap on sites
 * with many recurring sessions. The window (past_days, window_days) comes
 * from module settings.
 */
class Extractor extends ExtractorBase implements ExtractorInterface {

  /**
   * {@inheritdoc}
   */
  public function extract() {
    $window = $this->resolveSyncWindow();
    $instudio = $this->configFactory->get('yusaopeny_ymca360_instudio.settings');
    $pageSize = (int) ($instudio->get('sync.page_size') ?? 500);

    $this->logger->notice('[EXTRACTOR] Fetching YMCA360 schedules for window %from → %to.', [
      '%from' => gmdate('Y-m-d H:i:s', $window['from']) . 'Z',
      '%to' => gmdate('Y-m-d H:i:s', $window['to']) . 'Z',
    ]);

    $result = $this->client->getSchedulesWindowed($window['from'], $window['to'], $pageSize);
    $items = $result['items'] ?? [];
    $stats = $result['stats'] ?? [];

    $this->logger->info('[EXTRACTOR] Window fetch: %pages pages, %window items in window, API total %total.', [
      '%pages' => $stats['pages_fetched'] ?? 0,
      '%window' => $stats['window_items'] ?? 0,
      '%total' => $stats['api_total'] ?? 0,
    ]);

    if (!empty($items)) {
      $this->dataWrapper->setItems($items);
    }
    $this->dataWrapper->setSyncWindow($window['from'], $window['to']);
  }

  /**
   * Resolves the current sync window from config.
   *
   * @return array{from: int, to: int}
   */
  protected function resolveSyncWindow(): array {
    $instudio = $this->configFactory->get('yusaopeny_ymca360_instudio.settings');
    $pastDays = (int) ($instudio->get('sync.past_days') ?? 7);
    $windowDays = (int) ($instudio->get('sync.window_days') ?? 14);
    $now = time();
    return [
      'from' => $now - ($pastDays * 86400),
      'to' => $now + ($windowDays * 86400),
    ];
  }

}
