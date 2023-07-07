<?php

namespace Drupal\yusaopeny_ymca360;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelInterface;
use Exception;
use GuzzleHttp\Client;

/**
 * YMCA360 API Client.
 */
class Y360Client {

  /**
   * API Url from configuration.
   *
   * @var string
   */
  protected $api_url;

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
    $this->api_url = $this->config->get('api_url') ?: 'https://ymca360.org/api/external/v1/schedules';
  }

  /**
   * Verifies credentials by doing test request to the YMCA360 API.
   *
   * @return boolean|array
   *
   * @throws \Exception
   */
  public function verifyCredentials($creds) {
    try {
      $data = $this->doRequest(['size' => 1], ['auth' => array_values($creds)]);
    }
    catch (Exception $e) {
      return FALSE;
    }

    return $data;
  }

  /**
   * Get schedules filter form YMCA360 API response.
   *
   * @return array
   */
  public function getByScheduleFilter() {
    try {
      $data = $this->doRequest(['size' => 1]);
      return $data['summary']['facets']['schedule_ids'] ?? [];
    }
    catch (Exception $e) {
      $this->logger->warning('Unable to get data from YMCA360 API. %code - %msg', [
        '%msg' => $e->getMessage(),
        '%code' => $e->getCode(),
      ]);
      return [];
    }
  }

  /**
   * Get schedules from YMCA360 API.
   *
   * @see https://github.com/YMCA360/external-api-docs
   */
  public function getSchedules(int $size = 250, $filters = [], $limit = 5000) {
    $get_all = FALSE;
    $json = [];
    if ($size === 0) {
      $get_all = TRUE;
      $size = 500;
    }

    $queryParams = [
      'size' => $size,
      'page' => 0,
    ] + $filters;

    $schedule_mapping = $this->config->get('schedule.schedules');
    if (!empty($schedule_mapping) && is_array($schedule_mapping)) {
      foreach ($schedule_mapping as $schedule_id => $info) {
        if (!isset($info['enable']) || !$info['enable']) {
          continue;
        }
        $queryParams['schedule_id'][] = $schedule_id;
      }
    }

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
    } while ($get_all && $queryParams['page'] <= $pages && count($json['items']) < $limit);

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
  private function doRequest(array $params, array $options = []): array {
    $options = array_merge([
      'headers' => [
        'Accept' => 'application/json',
      ],
      'auth' => $this->getAuth(),
      'timeout' => 60,
    ], $options);

    $query_string = http_build_query($params);
    /**
     * at the moment Y360API does not support schedule_id[0] = {value} query params,
     * so I have to strip key from query string array-params to make it work.
     */
    $query_string = preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $query_string);
    $options['query'] = $query_string;
    $this->logger->info('Sending request to %uri', [
      '%uri' => $this->api_url . '?' . urldecode($query_string),
    ]);

    try {
      $response = $this->client->get($this->api_url, $options);
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
   * @return array<string>
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
