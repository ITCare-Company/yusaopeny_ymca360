<?php

namespace Drupal\yusaopeny_ymca360\syncer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\yusaopeny_ymca360\Y360Client;

/**
 * Extracts data from YMCA360 API.
 *
 * @package Drupal\yusaopeny_ymca360.
 */
abstract class ExtractorBase implements ExtractorInterface {

  /**
   * Config factory for accessing submodule-specific settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

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
   * Key/value factory for the syncer's circuit-breaker state.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected KeyValueFactoryInterface $keyValueFactory;

  /**
   * Extractor class constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Y360Client $client, DataWrapper $data_wrapper, LoggerChannelInterface $logger, KeyValueFactoryInterface $key_value_factory) {
    $this->configFactory = $config_factory;
    $this->config = $config_factory->get('yusaopeny_ymca360.settings');
    $this->client = $client;
    $this->dataWrapper = $data_wrapper;
    $this->logger = $logger;
    $this->keyValueFactory = $key_value_factory;
  }

  /**
   * Extracts the data from remote source.
   */
  abstract public function extract();

}
