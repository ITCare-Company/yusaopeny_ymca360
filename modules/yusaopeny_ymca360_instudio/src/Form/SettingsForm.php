<?php

namespace Drupal\yusaopeny_ymca360_instudio\Form;

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
    return 'yusaopeny_ymca360_instudio_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['yusaopeny_ymca360_instudio.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('yusaopeny_ymca360_instudio.settings');

    $form['cron'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Automatic Sync'),
      '#tree' => TRUE,
    ];
    $form['cron']['enable_cron'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable sync on cron runs'),
      '#default_value' => $config->get('cron.enable_cron'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('yusaopeny_ymca360_instudio.settings')
      ->set('cron', $form_state->getValue('cron'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
