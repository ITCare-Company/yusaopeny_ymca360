<?php

namespace Drupal\yusaopeny_ymca360\syncer;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\yusaopeny_ymca360\Y360MappingRepository;

/**
 * Loads prepared data received from YMCA360 API into storage.
 *
 * @package Drupal\yusaopeny_ymca360.
 */
abstract class LoaderBase implements LoaderInterface {

  const DEFAULT_ACTIVITY_CATEGORY = 63640;

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
   * Loader class constructor.
   */
  public function __construct(
    DataWrapper $data_wrapper,
    Y360MappingRepository $repository,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelInterface $logger,
    StateInterface $state
  )
  {
    $this->dataWrapper = $data_wrapper;
    $this->repository = $repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->logger = $logger;
    $this->state = $state;
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
    $this->logger->info('[LOADER] There are %total schedules from api to create', [
      '%total' => count($items),
    ]);
    foreach ($items as $item) {
      $this->createSession($item);
    }
  }

  /**
   * Processes items to be updated.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function updateItems(): void {
    $items = $this->dataWrapper->getItemsToUpdate();
    $this->logger->info('[LOADER] There are %total schedules from api to update', [
      '%total' => count($items),
    ]);
    foreach ($items as $mapping_id => $item) {
      $this->updateSession($mapping_id, $item);
    }
  }

  /**
   * Processes items to be deleted.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function deleteItems(): void {
    $items = $this->dataWrapper->getItemsToDelete();
    $this->logger->info('[LOADER] There are %total schedules from api to delete', [
      '%total' => count($items),
    ]);
    foreach ($items as $item_id) {
      $this->deleteSession($item_id);
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
    $session = Node::create([
      'uid' => 1,
      'lang' => 'und',
      'type' => 'session',
      'title' => $data['title'],
    ]);

    $location = $this->getLocation($data['branch_id']);

    $session->set('field_session_class', $this->getClass($data));
    $session->set('field_session_time', $this->getSessionTime($data));
    if ($location) {
      $session->set('field_session_location', ['target_id' => $location->id() ?? 0]);
    }
    $session->set('field_session_room', $data['studio_name']);
    $session->set('field_session_instructor', $data['instructor_name']);
    $session->set('field_session_description', $data['description']);
    $session->set('field_session_min_age', $data['min_age']);
    $session->set('field_session_max_age', $data['max_age']);
    $session->set('field_wait_list_availability', $data['wait_list_availability']);

    $session->setUnpublished();
    if ($data['published'] && $data['status'] === 'scheduled') {
      $session->setPublished();
    }

    \Drupal::moduleHandler()->alter('yusaopeny_ymca360_session', $session, $data);

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
    $session = $mapping->getSession();
    $location = $mapping->getLocation();
    $session->set('field_session_class', $this->getClass($item));
    $session->set('field_session_time', $this->getSessionTime($item));
    if ($location) {
      $session->set('field_session_location', ['target_id' => $location->id() ?? 0]);
    }
    $session->set('field_session_room', $item['studio_name']);
    $session->set('field_session_instructor', $item['instructor_name']);
    $session->set('field_session_description', $item['description']);
    $session->set('field_session_min_age', $item['min_age']);
    $session->set('field_session_max_age', $item['max_age']);
    $session->set('field_wait_list_availability', $item['wait_list_availability']);

    $session->setUnpublished();
    if ($item['published'] && $item['status'] === 'scheduled') {
      $session->setPublished();
    }

    \Drupal::moduleHandler()->alter('yusaopeny_ymca360_session', $session, $item);

    $session->save();

    $this->repository->update($item, $session, $location);
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
   * @param int $branch_id
   *   YMCA360 Branch ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Location or NULL.
   */
  protected function getLocation($branch_id) {
    static $map = [];
    if (empty($map)) {
      $locations_mapping = \Drupal::config('yusaopeny_ymca360.locations_mapping')->get('locations') ?? [];
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
    $day = (new DateTimePlus($data['start_at']))->format('l');

    $paragraphs = [];
    $paragraph = Paragraph::create(['type' => 'session_time']);
    $paragraph->set('field_session_time_days', [strtolower($day)]);
    $paragraph->set('field_session_time_date',
      [
        'value' => $this->repository->formatIsoDate($data['start_at']),
        'end_value' => $this->repository->formatIsoDate($data['end_at']),
      ]
    );
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
   * @param array $class
   *   Class data.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Class node.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getClass(array $class) {
    $activity_id = $this->getActivity($class['category_name']);
    // Try to find class.
    $existingClasses = $this->nodeStorage
      ->getQuery()
      ->condition('type', 'class')
      ->condition('title', $class['title'])
      ->condition('field_class_activity', $activity_id)
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($existingClasses)) {
      $class_id = reset($existingClasses);
      /** @var \Drupal\node\Entity\Node $class*/
      $class = $this->nodeStorage->load($class_id);
    }
    else {
      $class = $this->nodeStorage
        ->create([
          'uid' => 1,
          'lang' => 'und',
          'type' => 'class',
          'title' => $class['title'],
          'moderation_state' => 'published',
          'field_class_activity' => [['target_id' => $activity_id]],
        ]);
      $class->setPublished();
      $class->save();
    }

    return $class;
  }

  /**
   * Gets or creates Activity node.
   *
   * @param string $category
   *   Activity name to look for or create from.
   *
   * @return int
   *   Activity node ID.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getActivity(string $activityName): int {
    // Try to get existing activity.
    $existingActivities = $this->nodeStorage
      ->getQuery()
      ->condition('title', $activityName)
      ->condition('type', 'activity')
      ->accessCheck(FALSE)
      ->execute();

    if ($existingActivities) {
      return reset($existingActivities);
    }

    // No activities found. Create one.
    $activity = $this->nodeStorage->create([
      'uid' => 1,
      'lang' => 'und',
      'type' => 'activity',
      'title' => $activityName,
      'moderation_state' => 'published',
      'field_activity_category' => [['target_id' => $this->getActivityCategory()]],
    ]);
    $activity->setPublished();
    $activity->save();
    return $activity->id();
  }

  /**
   * Returns Activity Category Node id stored into state variable.
   *
   * @return int
   */
  abstract protected function getActivityCategory();

}
