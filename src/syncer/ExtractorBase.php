<?php

namespace Drupal\yusaopeny_ymca360\syncer;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\yusaopeny_ymca360\Y360Client;

/**
 * Extracts data from YMCA360 API.
 *
 * @package Drupal\yusaopeny_ymca360.
 */
abstract class ExtractorBase implements ExtractorInterface {

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Client.
   *
   * @var \Drupal\yusaopeny_ymca360\Y360Client
   */
  protected Y360Client $client;

  /**
   * DataWrapper.
   *
   * @var \Drupal\yusaopeny_ymca360\syncer\DataWrapper
   */
  protected DataWrapper $dataWrapper;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Extractor class constructor.
   */
  public function __construct(Y360Client $client, DataWrapper $data_wrapper, LoggerChannelInterface $logger) {
    $this->client = $client;
    $this->dataWrapper = $data_wrapper;
    $this->logger = $logger;
  }

  /**
   * Extracts the data from remote source.
   */
  abstract public function extract();

}
