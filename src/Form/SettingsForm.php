<?php

namespace Drupal\yusaopeny_ymca360\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\yusaopeny_ymca360\Y360Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure YMCA360 Integration settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Drupal State service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Module Handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $module_handler;

  /**
   * Y360Client service.
   *
   * @var \Drupal\yusaopeny_ymca360\Y360Client
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StateInterface $state,
    ModuleHandlerInterface $module_handler,
    Y360Client $client
  )
  {
    $this->state = $state;
    $this->module_handler = $module_handler;
    $this->client = $client;
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('module_handler'),
      $container->get('yusaopeny_ymca360.y360_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'yusaopeny_ymca360_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['yusaopeny_ymca360.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('yusaopeny_ymca360.settings');
    $form['credentials'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('API Credentials'),
      '#tree' => TRUE,
    ];

    $form['credentials']['user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User'),
      '#default_value' => $config->get('credentials.user'),
      '#required' => TRUE,
    ];
    $form['credentials']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t('Please reach out to YMCA 360 representative to obtain the API credentials.'),
      '#default_value' => $config->get('credentials.password'),
      '#attributes' => [
        'placeholder' => 'password',
        'value' => $config->get('credentials.password'),
      ],
    ];

    if ($schedule_options = $this->getScheduleOptions()) {
      $form['schedule'] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $this->t('Schedules to sync'),
        '#description' => $this->t('Select YMCA360 schedules you want to be synced down to your Open Y website and specify program subcategories they correspond to.'),
        '#tree' => TRUE,
      ];

      $form['schedule']['schedules'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('YMCA 360 Schedule'),
          $this->t('Local Program Subcategory'),
        ],
        '#description' => $this->t('Tick the schedules to sync'),
        '#empty' => $this->t('No schedules available'),
      ];

      $schedule_mapping = $config->get('schedule.schedules') ?? [];
      $keys_config = array_keys($schedule_mapping);
      $keys_schedule = array_keys($schedule_options);
      $keys = array_unique(array_merge($keys_config, $keys_schedule));
      sort($keys);

      foreach ($keys as $id) {
        $label = $schedule_options[$id] ?? '';
        $category = NULL;
        if ($category_id = $schedule_mapping[$id]['subcategory'] ?? NULL) {
          $category = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->load($category_id);
        }

        $form['schedule']['schedules'][$id]['enable'] = [
          '#type' => 'checkbox',
          '#title' => "$label ($id)",
          '#default_value' => (bool) $schedule_mapping[$id]['enable'],
        ];
        $form['schedule']['schedules'][$id]['subcategory'] = [
          '#type' => 'entity_autocomplete',
          '#title' => $this->t('Program subcategory'),
          '#title_display' => 'invisible',
          '#target_type' => 'node',
          '#default_value' => $category,
          '#selection_settings' => [
            'target_bundles' => ['program_subcategory'],
          ],
          '#states' => [
            'required' => [
              '[name="schedule[schedules][' . $id . '][enable]"]' => ['checked' => TRUE],
            ],
          ],
        ];
      }
    }

    $form['limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Import Limit'),
      '#required' => FALSE,
      '#options' => [
        0 => $this->t('No limit'),
        50 => $this->t('50 records'),
        100 => $this->t('100 records'),
        250 => $this->t('250 records'),
        500 => $this->t('500 records'),
      ],
      '#default_value' => $config->get('limit')
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $creds = $form_state->getValue('credentials');
    $data = $this->client->verifyCredentials($creds);
    if (!$data) {
      $form_state->setErrorByName('credentials', $this->t('Credentials are not valid.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('yusaopeny_ymca360.settings')
      ->set('credentials', $form_state->getValue('credentials'))
      ->set('schedule', $form_state->getValue('schedule') ?? [])
      ->set('limit', $form_state->getValue('limit'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Dummy schedule options.
   *
   * @return string[]
   *   An associative array of schedule options;
   */
  private function getScheduleOptions() {
    $options = $this->client->getByScheduleFilter();
    $result = [];
    if (!empty($options)) {
      array_map(function ($item) use (&$result) {
        $result[$item['id']] = $item['label'];
      }, $options);
    }
    return $result;
  }

}
