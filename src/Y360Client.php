<?php

namespace Drupal\yusaopeny_ymca360;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\Client;

/**
 * YMCA360 API Client.
 */
class Y360Client {

  const API_URI = 'https://staging.ymca360.org/api/external/v1/schedules';

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * Configuration array.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  public $logger;

  /**
   * Class constructor.
   */
  public function __construct(Client $client, ConfigFactoryInterface $configFactory, LoggerChannelInterface $logger) {
    $this->client = $client;
    $this->config = $configFactory->get('yusaopeny_ymca360.settings');
    $this->logger = $logger;
  }

  /**
   * Get schedules from YMCA360 API.
   *
   * @see https://github.com/YMCA360/external-api-docs
   */
  public function getSchedules($size = 100, $filters = []) {
    $getAll = FALSE;
    $json = [];
    if ($size === 'all') {
      $getAll = TRUE;
      $size = 100;
    }

    $queryParams = [
      'size' => $size,
      'page' => 0,
    ] + $filters;

    do {
      $data = $this->doRequest($queryParams);
      if ($queryParams['page'] === 0) {
        $json = $data;
        $pages = $data['summary']['total_pages'] ?? 1;
      }
      else {
        $json['items'] = array_merge($json['items'], $data['items']);
      }
      $queryParams['page']++;
      usleep(100000);
    } while ($getAll && $queryParams['page'] <= $pages);

    return $json;
  }

  /**
   * Performs external http request.
   *
   * @param array $params
   *   Params to be appended to the API Endpoint URI.
   *
   * @return array
   *   Array with data from YMCA360 API.
   */
  private function doRequest(array $params): array {
    $options = [
      'headers' => [
        'Accept' => 'application/json',
      ],
      'auth' => $this->getAuth(),
      'query' => $params,
      'timeout' => 60,
    ];

    $query_string = http_build_query($params);
    $this->logger->info('Sending request to %uri', [
      '%uri' => self::API_URI . '?' . $query_string,
    ]);

    try {
      $response = $this->client->get(self::API_URI, $options);
      $content = $response->getBody()->getContents();
      $json = json_decode($content, TRUE, JSON_THROW_ON_ERROR);
    }
    catch (\Exception $e) {
      $this->logger->warning('Unable to get data from YMCA360 API. %code - %msg', [
        '%msg' => $e->getMessage(),
        '%code' => $e->getCode(),
      ]);
      throw $e;
    }

    return $json;
  }

  /**
   * Generates Basic Auth digest.
   *
   * @return string
   *   Encoded basic auth string.
   */
  protected function getAuth() {
    $credentials = $this->config->get('credentials');
    return [
      $credentials['user'],
      $credentials['password'],
    ];
  }

}
