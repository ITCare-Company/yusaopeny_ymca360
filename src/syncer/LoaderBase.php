<?php

namespace Drupal\yusaopeny_ymca360\syncer;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\node\NodeInterface;
use Drupal\yusaopeny_ymca360\Y360MappingRepository;

/**
 * Loads prepared data received from YMCA360 API into storage.
 *
 * @package Drupal\yusaopeny_ymca360.
 */
abstract class LoaderBase implements LoaderInterface {

  /**
   * DataWrapper.
   *
   * @var \Drupal\yusaopeny_ymca360\syncer\DataWrapper
   */
  protected DataWrapper $dataWrapper;

  /**
   * YMCA360 Mapping Repository.
   *
   * @var \Drupal\yusaopeny_ymca360\Y360MappingRepository
   */
  protected Y360MappingRepository $repository;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Node Storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;


  /**
   * Drupal State service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Loader class constructor.
   */
  public function __construct(
    DataWrapper $data_wrapper,
    Y360MappingRepository $repository,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelInterface $logger,
    StateInterface $state,
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler
  )
  {
    $this->dataWrapper = $data_wrapper;
    $this->repository = $repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->logger = $logger;
    $this->state = $state;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritDoc}
   */
  public function load() {
    $this->createItems();
    $this->updateItems();
    $this->deleteItems();
  }

  /**
   * Processes items to be created.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createItems(): void {
    $items = $this->dataWrapper->getItemsToCreate();
    $this->logger->info('[LOADER] There are %total objects to create', [
      '%total' => count($items),
    ]);
    $_start = microtime(TRUE);
    foreach ($items as $item) {
      $this->createSession($item);
      if (microtime(true) - $_start > 60) {
        break;
      }
    }
  }

  /**
   * Processes items to be updated.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function updateItems(): void {
    $items = $this->dataWrapper->getItemsToUpdate();
    $this->logger->info('[LOADER] There are %total objects to update', [
      '%total' => count($items),
    ]);
    $_start = microtime(TRUE);
    foreach ($items as $mapping_id => $item) {
      $this->updateSession($mapping_id, $item);
      if (microtime(true) - $_start > 60) {
        break;
      }
    }
  }

  /**
   * Processes items to be deleted.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function deleteItems(): void {
    $items = $this->dataWrapper->getItemsToDelete();
    $this->logger->info('[LOADER] There are %total objects to delete', [
      '%total' => count($items),
    ]);
    $_start = microtime(TRUE);
    foreach ($items as $item_id) {
      $this->deleteSession($item_id);
      if (microtime(true) - $_start > 60) {
        break;
      }
    }
  }

  /**
   * Creates Session Node.
   *
   * @param array $data
   *   Array with data to create the Session Node.
   *
   * @return \Drupal\Node\NodeInterface
   *   Returns created Session node.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createSession(array $data): NodeInterface {
    $session = $this->entityTypeManager
      ->getStorage('node')
      ->create(['type' => 'session']);

    $session->setTitle($this->getSessionTitle($data));
    $session->set('field_session_class', $this->getClass($data));
    $session->set('field_session_time', $this->getSessionTime($data));
    $location = $this->getLocation($data['branch_id'], $data['kind']);
    if ($location) {
      $session->set('field_session_location', ['target_id' => $location->id() ?? 0]);
    }
    $session->set('field_session_room', $data['studio_name']);
    $session->set('field_session_instructor', $data['instructor_name']);
    $session->set('field_session_description', $data['description']);
    $session->set('field_session_min_age', $data['min_age']);
    $session->set('field_session_max_age', $data['max_age']);
    if ($session->hasField('field_wait_list_availability')) {
      $session->set('field_wait_list_availability', $data['wait_list_availability']);
    }

    $session->setUnpublished();
    if ($this->isPublishedSession($data)) {
      $session->setPublished();
    }

    $this->moduleHandler->alter('yusaopeny_ymca360_session', $session, $data);

    $session->save();
    $this->repository->create($data, $session, $location);

    return $session;
  }

  /**
   * Updates Session Node.
   *
   * @param int $mapping_id
   *   Y360Mapping ID.
   * @param array $item
   *   Array with item data to be updated.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function updateSession($mapping_id, array $item): void {
    /** @var \Drupal\yusaopeny_ymca360\Entity\Y360Mapping $mapping */
    $mapping = $this->repository->getStorage()->load($mapping_id);
    /** @var \Drupal\node\NodeInterface $session */
    $session = $mapping->getSession();
    if (!$session) {
      $session = $this->entityTypeManager
        ->getStorage('node')
        ->create(['type' => 'session']);
    }
    $session->setTitle($this->getSessionTitle($item));
    $session->set('field_session_class', $this->getClass($item));
    $session->set('field_session_time', $this->getSessionTime($item));
    $location = $this->getLocation($item['branch_id'], $item['kind']);
    if ($location) {
      $session->set('field_session_location', ['target_id' => $location->id() ?? 0]);
    }
    $session->set('field_session_room', $item['studio_name']);
    $session->set('field_session_instructor', $item['instructor_name']);
    $session->set('field_session_description', $item['description']);
    $session->set('field_session_min_age', $item['min_age']);
    $session->set('field_session_max_age', $item['max_age']);
    if ($session->hasField('field_wait_list_availability')) {
      $session->set('field_wait_list_availability', $item['wait_list_availability']);
    }

    $session->setUnpublished();
    if ($this->isPublishedSession($item)) {
      $session->setPublished();
    }

    $this->moduleHandler->alter('yusaopeny_ymca360_session', $session, $item);

    $session->save();

    $this->repository->update($item, $session, $location);
  }

  /**
   * Builds session title.
   *
   * @param array $item
   *   Session item data.
   *
   * @return mixed|string
   *   Session title.
   */
  protected function getSessionTitle(array $item) {
    $title = $item['title'];
    if ($item['status'] === 'canceled') {
      $title = 'CANCELED: ' . $title;
    }

    return $title;
  }

  /**
   * Checks if the session should be published.
   *
   * @param array $item
   *   Session item data.
   *
   * @return bool
   *   True if session is supposed to be published.
   */
  protected function isPublishedSession(array $item): bool {
    return $item['published'] && in_array($item['status'], ['scheduled', 'canceled']);
  }

  /**
   * Deletes Session Node.
   *
   * @param int $mapping_id
   *   Y360Mapping ID.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function deleteSession(int $mapping_id): void {
    /** @var \Drupal\yusaopeny_ymca360\Entity\Y360Mapping $mapping */
    $mapping = $this->repository->getStorage()->load($mapping_id);
    $session = $mapping->getSession();
    $session->delete();
    $this->repository->delete($mapping_id);
  }

  /**
   * Gets Location ID from mapping settings.
   *
   * @param int|null $branch_id
   *   YMCA360 Branch ID.
   * @param string|null $kind
   *   YMCA360 event type.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Location or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getLocation(?int $branch_id, ?string $kind): ?EntityInterface {
    static $map = [];

    if ($kind === 'Live Stream') {
      return $this->getLivestreamLocation();
    }

    if (empty($map)) {
      $locations_mapping = $this->configFactory->get('yusaopeny_ymca360.locations_mapping')->get('locations') ?? [];
      array_map(function ($item) use (&$map) {
        $pieces = explode(',', $item);
        $location = $this->nodeStorage->loadByProperties(['title' => $pieces[1]]);
        if (is_array($location) && !empty($location)) {
          $location = reset($location);
          // Convert location Title into location ID.
          $map[$pieces[0]] = $location;
        }
      }, $locations_mapping);
    }
    return $map[$branch_id] ?? NULL;
  }

  /**
   * Returns the Livestream location.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The virtual location entity if set.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLivestreamLocation(): ?EntityInterface {
    if (!$id = $this->configFactory->get('yusaopeny_ymca360.locations_mapping')->get('virtual_location')) {
      return NULL;
    }
    return $this->nodeStorage->load($id);
  }


  /**
   * Creates Session Time paragraph.
   *
   * @param array $data
   *   Array with source data.
   *
   * @return array
   *   Array with paragraphs references.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getSessionTime(array $data) {
    $timezone = $this->configFactory->get('system.date')->get('timezone')['default'];
    $day = (new DateTimePlus($data['start_at']))->setTimezone(new \DateTimeZone($timezone))->format('l');

    $paragraphs = [];
    $paragraph = $this->entityTypeManager
      ->getStorage('paragraph')
      ->create(['type' => 'session_time']);
    $paragraph->set('field_session_time_days', [strtolower($day)]);
    $paragraph->set('field_session_time_date', [
      'value' => $this->repository->formatIsoDate($data['start_at']),
      'end_value' => $this->repository->formatIsoDate($data['end_at']),
    ]);
    $paragraph->isNew();
    $paragraph->save();

    $paragraphs[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];

    return $paragraphs;
  }

  /**
   * Creates class or use existing.
   *
   * @param array $data
   *   Class data.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Class node.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getClass(array $data) {
    $activity_id = $this->getActivity((string) ($data['category_name'] ?? 'Uncategorized'), (int) $data['schedule_id']);
    // Try to find class.
    $existing_classes = $this->nodeStorage
      ->getQuery()
      ->condition('type', 'class')
      ->condition('title', $data['title'])
      ->condition('field_class_activity', $activity_id)
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($existing_classes)) {
      $class_id = reset($existing_classes);
      /** @var \Drupal\node\Entity\Node $class*/
      $class = $this->nodeStorage->load($class_id);
      $class->set('field_class_description', $data['description']);
      $class->save();
    }
    else {
      $class = $this->nodeStorage
        ->create([
          'type' => 'class',
          'title' => $data['title'],
          'moderation_state' => 'published',
          'field_class_activity' => [['target_id' => $activity_id]],
        ]);
      $class->set('field_class_description', $data['description']);
      $class->setPublished();

      $this->moduleHandler->alter('yusaopeny_ymca360_class_create', $class, $data);

      $class->save();
    }

    return $class;
  }

  /**
   * Gets or creates Activity node.
   *
   * @param string $activity_name
   *   The activity name.
   * @param int $schedule_id
   *   The source schedule ID.
   *
   * @return int
   *   Activity node ID.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getActivity(string $activity_name, int $schedule_id): int {
    // Try to get existing activity.
    $existingActivities = $this->nodeStorage
      ->getQuery()
      ->condition('title', $activity_name)
      ->condition('type', 'activity')
      ->condition('field_activity_category', $this->getActivityCategory($schedule_id))
      ->accessCheck(FALSE)
      ->execute();

    if ($existingActivities) {
      return reset($existingActivities);
    }

    // No activities found. Create one.
    $activity = $this->nodeStorage->create([
      'type' => 'activity',
      'title' => $activity_name,
      'moderation_state' => 'published',
      'field_activity_category' => [['target_id' => $this->getActivityCategory($schedule_id)]],
    ]);
    $activity->setPublished();

    $this->moduleHandler->alter('yusaopeny_ymca360_activity_create', $activity);

    $activity->save();
    return $activity->id();
  }

  /**
   * Returns Activity Category Node id stored into state variable.
   *
   * @param int $schedule_id
   *   The source schedule ID.
   *
   * @return int
   */
  protected function getActivityCategory(int $schedule_id): int {
    $config = $this->configFactory->get('yusaopeny_ymca360.settings')->get('schedule.schedules');
    return (int) $config[$schedule_id]['subcategory'] ?? 0;
  }

}
