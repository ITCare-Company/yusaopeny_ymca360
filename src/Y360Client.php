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
   * API endpoint URL.
   *
   * @var string
   */
  protected string $apiUrl;

  /**
   * HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * Module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  public LoggerChannelInterface $logger;

  public function __construct(Client $client, ConfigFactoryInterface $configFactory, LoggerChannelInterface $logger) {
    $this->client = $client;
    $this->config = $configFactory->get('yusaopeny_ymca360.settings');
    $this->logger = $logger;
    $this->apiUrl = $this->config->get('api_url') ?: 'https://ymca360.org/api/external/v1/schedules';
  }

  /**
   * Verifies credentials by doing test request to the YMCA360 API.
   *
   * @param array $creds
   *   Credentials [user, password].
   *
   * @return bool|array
   *   Response data on success, FALSE on failure.
   */
  public function verifyCredentials(array $creds) {
    try {
      return $this->doRequest(['size' => 1], ['auth' => array_values($creds)]);
    }
    catch (Exception $e) {
      return FALSE;
    }
  }

  /**
   * Returns schedule filter facets from the YMCA360 API.
   *
   * @return array
   *   Schedule facets, empty array on failure.
   */
  public function getByScheduleFilter(): array {
    try {
      $data = $this->doRequest(['size' => 1]);
      return $data['summary']['facets']['schedule_ids'] ?? [];
    }
    catch (Exception $e) {
      $this->logApiFailure($e);
      return [];
    }
  }

  /**
   * Fetches schedules within a time window.
   *
   * API has no server-side date filter, so we paginate ascending by start_at
   * and break as soon as we pass the window's upper bound.
   *
   * @param int $fromTimestamp
   *   Window start (UNIX timestamp, UTC). Items before this are discarded.
   * @param int $toTimestamp
   *   Window end (UNIX timestamp, UTC). Pagination stops once an item's
   *   start_at exceeds this value.
   * @param int $pageSize
   *   Page size used for API pagination.
   *
   * @return array
   *   ['items' => [...], 'stats' => ['pages_fetched' => N, 'api_total' => N, 'window_items' => N]]
   */
  public function getSchedulesWindowed(int $fromTimestamp, int $toTimestamp, int $pageSize = 500): array {
    $queryParams = [
      'size' => $pageSize,
      'page' => 0,
      'sort_by' => 'start_at',
    ];
    $scheduleIds = $this->getEnabledScheduleIds();
    if (!empty($scheduleIds)) {
      $queryParams['schedule_id'] = $scheduleIds;
    }

    $items = [];
    $totalPages = 1;
    $apiTotal = 0;
    $pagesFetched = 0;

    do {
      $data = $this->doRequest($queryParams);
      $pagesFetched++;
      if ($queryParams['page'] === 0) {
        $totalPages = $data['summary']['total_pages'] ?? 1;
        $apiTotal = $data['summary']['total_items'] ?? count($data['items'] ?? []);
      }

      $pageItems = $data['items'] ?? [];
      if (empty($pageItems)) {
        break;
      }

      [$windowItems, $shouldStop] = $this->filterPageByWindow($pageItems, $fromTimestamp, $toTimestamp);
      if (!empty($windowItems)) {
        $items = array_merge($items, $windowItems);
      }
      if ($shouldStop) {
        break;
      }

      $queryParams['page']++;
      usleep(100000);
    } while ($queryParams['page'] < $totalPages);

    return [
      'items' => $items,
      'stats' => [
        'pages_fetched' => $pagesFetched,
        'api_total' => $apiTotal,
        'window_items' => count($items),
      ],
    ];
  }

  /**
   * Legacy fetch without time window.
   *
   * Kept for BC (facets, verification). Prefer getSchedulesWindowed() for syncing.
   */
  public function getSchedules(int $size = 250, array $filters = [], int $limit = 5000): array {
    $queryParams = [
      'size' => $size,
      'page' => 0,
    ] + $filters;
    $scheduleIds = $this->getEnabledScheduleIds();
    if (!empty($scheduleIds)) {
      $queryParams['schedule_id'] = $scheduleIds;
    }

    $json = $this->doRequest($queryParams);
    if ($size !== 0) {
      return $json;
    }

    $totalPages = $json['summary']['total_pages'] ?? 1;
    $queryParams['page'] = 1;
    while ($queryParams['page'] < $totalPages && count($json['items']) < $limit) {
      $data = $this->doRequest($queryParams);
      $json['items'] = array_merge($json['items'], $data['items']);
      $queryParams['page']++;
      usleep(100000);
    }
    return $json;
  }

  /**
   * Returns enabled schedule IDs from config.
   */
  protected function getEnabledScheduleIds(): array {
    $scheduleMapping = $this->config->get('schedule.schedules');
    if (!is_array($scheduleMapping)) {
      return [];
    }
    $ids = [];
    foreach ($scheduleMapping as $scheduleId => $info) {
      if (!empty($info['enable'])) {
        $ids[] = $scheduleId;
      }
    }
    return $ids;
  }

  /**
   * Filters a page of items against the window.
   *
   * Items before window_from are discarded. First item strictly past
   * window_to signals end-of-window (list is sorted ASC by start_at).
   *
   * @return array{0: array, 1: bool}
   *   Tuple [filtered_items, stop_pagination].
   */
  protected function filterPageByWindow(array $pageItems, int $from, int $to): array {
    $windowItems = [];
    foreach ($pageItems as $item) {
      $startAt = $this->itemStartTimestamp($item);
      if ($startAt === NULL) {
        continue;
      }
      if ($startAt > $to) {
        return [$windowItems, TRUE];
      }
      if ($startAt < $from) {
        continue;
      }
      $windowItems[] = $item;
    }
    return [$windowItems, FALSE];
  }

  /**
   * Parses item's start_at into UNIX timestamp.
   */
  protected function itemStartTimestamp(array $item): ?int {
    if (empty($item['start_at'])) {
      return NULL;
    }
    $ts = strtotime($item['start_at']);
    return $ts !== FALSE ? $ts : NULL;
  }

  /**
   * Performs an external HTTP request.
   */
  private function doRequest(array $params, array $options = []): array {
    $options = array_merge([
      'headers' => ['Accept' => 'application/json'],
      'auth' => $this->getAuth(),
      'timeout' => 60,
    ], $options);

    $queryString = $this->buildQueryString($params);
    $options['query'] = $queryString;
    $this->logger->info('Sending request to %uri', [
      '%uri' => $this->apiUrl . '?' . urldecode($queryString),
    ]);

    try {
      $response = $this->client->get($this->apiUrl, $options);
      $content = $response->getBody()->getContents();
      return json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\Exception $e) {
      $this->logApiFailure($e);
      throw $e;
    }
  }

  /**
   * Builds query string, stripping numeric indices from array params.
   *
   * Y360 API expects schedule_id[]=X repeated, not schedule_id[0]=X.
   */
  protected function buildQueryString(array $params): string {
    $queryString = http_build_query($params);
    return preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $queryString);
  }

  /**
   * Logs an API failure uniformly.
   */
  protected function logApiFailure(\Exception $e): void {
    $this->logger->warning('Unable to get data from YMCA360 API. %code - %msg', [
      '%msg' => $e->getMessage(),
      '%code' => $e->getCode(),
    ]);
  }

  /**
   * Returns [user, password] for basic auth.
   */
  protected function getAuth(): array {
    $credentials = $this->config->get('credentials') ?? [];
    return [$credentials['user'] ?? '', $credentials['password'] ?? ''];
  }

}
