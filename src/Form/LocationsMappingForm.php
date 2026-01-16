<?php

namespace Drupal\yusaopeny_ymca360\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\yusaopeny_ymca360\Y360Client;
use Drupal\yusaopeny_ymca360\Y360MappingRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure YMCA360 Integration locations mapping.
 */
class LocationsMappingForm extends ConfigFormBase {

  /**
   * Node Storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The YMCA360 client.
   *
   * @var \Drupal\yusaopeny_ymca360\Y360Client
   */
  protected $client;

  /**
   * Mapping repository.
   *
   * @var \Drupal\yusaopeny_ymca360\Y360MappingRepository
   */
  protected Y360MappingRepository $mappingRepository;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config, EntityTypeManagerInterface $entityTypeManager, Y360Client $client, Y360MappingRepository $repository) {
    parent::__construct($config_factory, $typed_config);
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->client = $client;
    $this->mappingRepository = $repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('yusaopeny_ymca360.y360_client'),
      $container->get('yusaopeny_ymca360.mapping_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'yusaopeny_ymca360_locations_mapping';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['yusaopeny_ymca360.locations_mapping'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $location_id = $this->config('yusaopeny_ymca360.locations_mapping')->get('virtual_location');
    $location = NULL;
    if ($location_id) {
      $location = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($location_id);
    }
    $form['virtual_location'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Virtual location'),
      '#description' => $this->t('Live stream events (session nodes) will be assigned to this location. Live streams will not appear in schedules unless the location field value is set.'),
      '#target_type' => 'node',
      '#default_value' => $location,
      '#selection_settings' => [
        'target_bundles' => ['branch'],
      ],
    ];

    $form['locations'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Locations mapping'),
      '#description' => $this->t('One per line. Format: YMCA360 Location ID, Name (as Branch in Drupal). Example: 202,West YMCA'),
      '#rows' => 15,
      '#default_value' => implode(PHP_EOL, $this->config('yusaopeny_ymca360.locations_mapping')->get('locations') ?? $this->getInitialValues()),
    ];

    $form['branches_preview'] = $this->addBranchesPreview();

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds branches preview form element.
   *
   * @return array
   *   Form element.
   */
  private function addBranchesPreview() {
    try {
      $data = $this->client->getSchedules(1);
      $data = $data['summary']['facets']['branch_ids'];
      usort($data, function ($a, $b) {
        return $a['id'] <=> $b['id'];
      });
      $data = array_map(function ($branch) {
        return sprintf("%' 5s", $branch['id']) . ' : ' . $branch['label'];
      }, $data);
      array_unshift($data, sprintf("%' 5s", '-----') . ' : ' . '-------------');
      array_unshift($data, sprintf("%' 5s", 'ID') . ' : ' . 'Location name');
    }
    catch (\Exception $e) {
      $data = ['Please verify your credentials'];
    }
    return [
      'wrapper' => [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $this->t('YMCA360 API locations for reference'),
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => implode(PHP_EOL, $data),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $locations = $form_state->getValue('locations');
    $locations = explode("\r\n", $locations);
    $this->config('yusaopeny_ymca360.locations_mapping')
      ->set('locations', $locations)
      ->set('virtual_location', $form_state->getValue('virtual_location'))
      ->save();
    $this->mappingRepository->resetHashes();
    parent::submitForm($form, $form_state);
  }

  /**
   * Gets pre-populated array of Locations from the database.
   *
   * @return string[]
   *   Array with prepared strings.
   */
  private function getInitialValues() {
    $nodes = $this->nodeStorage->loadByProperties(['type' => 'branch']);
    $values = array_map(function ($item) {
      return "0,{$item->label()}";
    }, $nodes);
    return $values;
  }

}
