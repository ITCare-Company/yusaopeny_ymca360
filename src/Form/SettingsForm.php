<?php

namespace Drupal\yusaopeny_ymca360\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
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
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StateInterface $state,
    ModuleHandlerInterface $module_handler
  )
  {
    $this->state = $state;
    $this->module_handler = $module_handler;
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
    if ($this->module_handler->moduleExists('yusaopeny_ymca360_instudio')) {
      if (!$this->state->get('yusaopeny_ymca360_instudio.program_subcategory', NULL)) {
        $this->messenger()->addError(
          $this->t('Please set Program Subcategory for Instudio Activities to be imported <a href="@url">here</a>.',
            ['@url' => Url::fromRoute('yusaopeny_ymca360_instudio.settings_form')->toString()])
        );
      }
    }

    if ($this->module_handler->moduleExists('yusaopeny_ymca360_livestreams')) {
      if (!$this->state->get('yusaopeny_ymca360_livestreams.program_subcategory', NULL)) {
        $this->messenger()->addError(
          $this->t('Please set Program Subcategory for Livestream Activities to be imported <a href="@url">here</a>.',
            ['@url' => Url::fromRoute('yusaopeny_ymca360_livestreams.settings_form')->toString()])
        );
      }
    }

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
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $config->get('credentials.password'),
      '#required' => TRUE,
    ];

    $form['schedule'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Schedules to sync'),
      '#description' => $this->t('Tick schedules you want to sync down to your Open Y website'),
      '#tree' => TRUE,
    ];
    $form['schedule']['schedules'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Schedules'),
      '#options' => $this->getScheduleOptions(),
      '#default_value' => $config->get('schedule.schedules'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $creds = $form_state->getValue('credentials');
    $valid = \Drupal::service('yusaopeny_ymca360.y360_client')->verifyCredentials($creds);
    if (!$valid) {
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
      ->set('schedule', $form_state->getValue('schedule'))
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
    return [
      'primary' => 'Primary schedule',
      'secondary' => 'Secondary schedule',
    ];
  }

}
