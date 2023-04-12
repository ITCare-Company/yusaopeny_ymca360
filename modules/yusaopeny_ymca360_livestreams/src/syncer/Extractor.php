<?php

namespace Drupal\yusaopeny_ymca360_livestreams\syncer;

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

    // @todo Fix: real `kind` value TBC.
    $data = $this->client->getSchedules(100, ['kind' => 'Livestreams']);
    if (!empty($data['items'])) {
      $this->logger->debug('[EXTRACTOR] There are %total schedules from api for processing', [
        '%total' => count($data['items']),
      ]);
      $this->dataWrapper->setItems($data['items']);
    }
  }

}
