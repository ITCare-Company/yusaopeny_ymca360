<?php

namespace Drupal\yusaopeny_ymca360\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure YMCA360 Integration settings for this site.
 */
class SettingsForm extends ConfigFormBase {

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
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $config->get('credentials.password'),
      '#required' => TRUE,
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('yusaopeny_ymca360.settings')
      ->set('credentials', $form_state->getValue('credentials'))
      ->set('cron', $form_state->getValue('cron'))
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
