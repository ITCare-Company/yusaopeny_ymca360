<?php

namespace Drupal\yusaopeny_ymca360_instudio\syncer;

use Drupal\yusaopeny_ymca360\syncer\ExtractorBase;
use Drupal\yusaopeny_ymca360\syncer\ExtractorInterface;

/**
 * Fetches in-studio session data from the YMCA360 API.
 *
 * Uses the API's native start_at / end_at / scheduled_from filters to pull
 * only the items that fall inside the sync window, plus an optional
 * updated_at filter for incremental cron runs.
 */
class Extractor extends ExtractorBase implements ExtractorInterface {

  const STATE_LAST_SYNC_TS = 'yusaopeny_ymca360_instudio.last_sync_ts';

  /**
   * {@inheritdoc}
   */
  public function extract() {
    $instudio = $this->configFactory->get('yusaopeny_ymca360_instudio.settings');
    $window = $this->resolveSyncWindow($instudio);
    $pageSize = (int) ($instudio->get('sync.page_size') ?? 500);
    $updatedSince = $this->resolveUpdatedSince($instudio);

    $this->logger->notice('[EXTRACTOR] Fetching YMCA360 schedules. Window %from → %to%incremental.', [
      '%from' => gmdate('Y-m-d H:i:s', $window['from']) . 'Z',
      '%to' => gmdate('Y-m-d H:i:s', $window['to']) . 'Z',
      '%incremental' => $updatedSince
        ? ', updated since ' . gmdate('Y-m-d H:i:s', $updatedSince) . 'Z'
        : ' (full fetch)',
    ]);

    $runStartedAt = time();
    $result = $this->client->getSchedulesWindowed($window['from'], $window['to'], $pageSize, $updatedSince);
    $items = $result['items'] ?? [];
    $stats = $result['stats'] ?? [];

    $this->logger->info('[EXTRACTOR] Window fetch: %pages pages, %total items returned by API.', [
      '%pages' => $stats['pages_fetched'] ?? 0,
      '%total' => $stats['api_total'] ?? 0,
    ]);

    if (!empty($items)) {
      $this->dataWrapper->setItems($items);
    }
    $this->dataWrapper->setSyncWindow($window['from'], $window['to']);
    $this->dataWrapper->setFullFetch($updatedSince === NULL);
    $this->dataWrapper->setMaxDeletesPerRun((int) ($instudio->get('sync.max_deletes_per_run') ?? 500));
    \Drupal::state()->set(self::STATE_LAST_SYNC_TS, $runStartedAt);
  }

  /**
   * Resolves the current sync window from config.
   *
   * @return array{from: int, to: int}
   */
  protected function resolveSyncWindow($instudio): array {
    $pastDays = (int) ($instudio->get('sync.past_days') ?? 7);
    $windowDays = (int) ($instudio->get('sync.window_days') ?? 14);
    $now = time();
    return [
      'from' => $now - ($pastDays * 86400),
      'to' => $now + ($windowDays * 86400),
    ];
  }

  /**
   * Resolves incremental sync cursor.
   *
   * Returns NULL for a full fetch (first run, or incremental disabled).
   */
  protected function resolveUpdatedSince($instudio): ?int {
    if (!$instudio->get('sync.incremental')) {
      return NULL;
    }
    $lastSync = \Drupal::state()->get(self::STATE_LAST_SYNC_TS);
    return is_numeric($lastSync) ? (int) $lastSync : NULL;
  }

}
