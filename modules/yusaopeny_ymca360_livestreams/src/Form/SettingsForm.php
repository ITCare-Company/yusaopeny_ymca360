<?php

namespace Drupal\yusaopeny_ymca360_livestreams\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\node\Entity\Node;
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
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config, StateInterface $state) {
    $this->state = $state;
    parent::__construct($config_factory, $typed_config);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'yusaopeny_ymca360_livestreams_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['yusaopeny_ymca360_livestreams.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('yusaopeny_ymca360_livestreams.settings');

    $program_subcategory = $this->state->get('yusaopeny_ymca360_livestreams.program_subcategory', NULL);
    if ($program_subcategory) {
      $program_subcategory = Node::load($program_subcategory);
    }

    $form['program_subcategory'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#tags' => FALSE,
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['program_subcategory'],
      ],
      '#default_value' => $program_subcategory,
      '#title' => $this->t('Program Subcategory'),
      '#description' => $this->t('Please specify Program Subcategory node for Activities created during sync'),
    ];

    $form['cron'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('CRON options'),
      '#tree' => TRUE,
    ];
    $form['cron']['enable_cron'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable CRON Job'),
      '#default_value' => $config->get('cron.enable_cron'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('yusaopeny_ymca360_livestreams.settings')
      ->set('cron', $form_state->getValue('cron'))
      ->save();

    $this->state->set('yusaopeny_ymca360_livestreams.program_subcategory', $form_state->getValue('program_subcategory'));
    parent::submitForm($form, $form_state);
  }
}
