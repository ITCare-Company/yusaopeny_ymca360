<?php

namespace Drupal\yusaopeny_ymca360_instudio\syncer;

use Drupal\yusaopeny_ymca360\syncer\ExtractorBase;
use Drupal\yusaopeny_ymca360\syncer\ExtractorInterface;

/**
 * {@inheritDoc}
 */
class Extractor extends ExtractorBase implements ExtractorInterface {

  /**
   * {@inheritdoc}
   */
  public function extract() {
    $this->logger->notice('[EXTRACTOR] Fetching data from YMCA360 API.');

    $limit = (int) $this->config->get('limit') ?? 100;
    $data = $this->client->getSchedules($limit, [
      'sort_by' => 'start_at',
    ]);

    if (!empty($data['items'])) {
      $this->logger->info('[EXTRACTOR] There are %total items for processing', [
        '%total' => count($data['items']),
      ]);
      $this->dataWrapper->setItems($data['items']);
    }
  }

}
