<?php

namespace Drupal\yusaopeny_ymca360\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\yusaopeny_ymca360\Y360Client;
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
   * Constructs a \Drupal\system\ConfigFormBase object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager, Y360Client $client) {
    parent::__construct($config_factory);
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('yusaopeny_ymca360.y360_client'),
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
    $form['locations'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Locations mapping'),
      '#description' => $this->t('One per line. Format: YMCA360 Location ID, Name (as Branch in Drupal). Example: 202,West YMCA'),
      '#rows' => 30,
      '#cols' => 50,
      '#default_value' => implode(PHP_EOL, $this->config('yusaopeny_ymca360.locations_mapping')->get('locations') ?? $this->getInitialValues()),
    ];

    $form['branches_preview'] = $this->addBranchesPreview();

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds branches preview form element.
   *
   * @return string[]
   *   Form element.
   */
  private function addBranchesPreview() {
    try {
      $data = $this->client->getSchedules(1);
      $data = $data['summary']['facets']['branch_ids'];
      $data = array_map(function ($branch) {
          return $branch['id'] . ':' . $branch['label'];
      }, $data);
    }
    catch (\Exception $e) {
      $data = ['Please verify your credentials'];
    }
    return [
      '#type' => 'markup',
      '#markup' => '<pre>' . implode(PHP_EOL, $data) . '</pre>',
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
      ->save();
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
