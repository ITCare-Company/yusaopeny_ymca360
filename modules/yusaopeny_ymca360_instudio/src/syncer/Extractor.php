<?php

namespace Drupal\yusaopeny_ymca360_instudio\syncer;

use Drupal\yusaopeny_ymca360\syncer\ExtractorBase;
use Drupal\yusaopeny_ymca360\syncer\ExtractorInterface;

/**
 * Fetches in-studio session data from the YMCA360 API.
 *
 * Uses the API's native start_at / end_at / scheduled_from filters to pull
 * only the items that fall inside the sync window.
 */
class Extractor extends ExtractorBase implements ExtractorInterface {

  /**
   * {@inheritdoc}
   */
  public function extract() {
    $instudio = $this->configFactory->get('yusaopeny_ymca360_instudio.settings');
    $window = $this->resolveSyncWindow($instudio);
    $pageSize = (int) ($instudio->get('sync.page_size') ?? 500);

    $this->logger->notice('[EXTRACTOR] Fetching YMCA360 schedules. Window %from → %to.', [
      '%from' => gmdate(\DateTimeInterface::ATOM, $window['from']),
      '%to' => gmdate(\DateTimeInterface::ATOM, $window['to']),
    ]);

    $result = $this->client->getSchedulesWindowed($window['from'], $window['to'], $pageSize);
    $items = $result['items'] ?? [];
    $stats = $result['stats'] ?? [];

    $this->logger->info('[EXTRACTOR] Window fetch: %pages pages, %total items returned by API.', [
      '%pages' => $stats['pages_fetched'] ?? 0,
      '%total' => $stats['api_total'] ?? 0,
    ]);

    $this->dataWrapper->setItems($items);
    $this->dataWrapper->setSyncWindow($window['from'], $window['to']);
    $this->dataWrapper->setMaxDeletesPerRun((int) ($instudio->get('sync.max_deletes_per_run') ?? 500));
  }

  /**
   * Resolves the current sync window from config.
   *
   * @return array{from: int, to: int}
   */
  protected function resolveSyncWindow($instudio): array {
    $windowDays = (int) ($instudio->get('sync.window_days') ?? 14);
    $now = time();
    return [
      'from' => $now,
      'to' => $now + ($windowDays * 86400),
    ];
  }

}
