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

    $form['sync'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Sync Window'),
      '#description' => $this->t('Only sessions whose start time falls inside this window are pulled from the YMCA360 API and reconciled in Drupal. Sessions outside the window are left untouched by regular syncs.'),
      '#tree' => TRUE,
    ];
    $form['sync']['past_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Past days'),
      '#description' => $this->t('How many days back from today to include in the sync window.'),
      '#default_value' => $config->get('sync.past_days') ?? 7,
      '#min' => 0,
      '#max' => 365,
      '#step' => 1,
    ];
    $form['sync']['window_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Future days (window size)'),
      '#description' => $this->t('How many days ahead of today to include in the sync window.'),
      '#default_value' => $config->get('sync.window_days') ?? 14,
      '#min' => 1,
      '#max' => 365,
      '#step' => 1,
    ];
    $form['sync']['page_size'] = [
      '#type' => 'number',
      '#title' => $this->t('API page size'),
      '#description' => $this->t('Number of items fetched per API page. Larger pages = fewer requests but more memory per request.'),
      '#default_value' => $config->get('sync.page_size') ?? 500,
      '#min' => 50,
      '#max' => 1000,
      '#step' => 50,
    ];
    $form['sync']['incremental'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Incremental sync'),
      '#description' => $this->t('Only fetch items updated since the last sync run (API <code>updated_at</code> filter). First run after enabling still performs a full fetch. Recommended for frequent cron runs.'),
      '#default_value' => $config->get('sync.incremental') ?? 0,
    ];
    $form['sync']['orphan_delete_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete orphan mappings (safety net)'),
      '#description' => $this->t('When enabled, mappings whose occurrence falls inside the sync window but no longer appears in the API response are removed. Leave off unless you trust the API <code>status=deleted</code> signal alone and you are sure every relevant schedule is in the enabled list below.'),
      '#default_value' => $config->get('sync.orphan_delete_enabled') ?? 0,
    ];
    $form['sync']['max_deletes_per_run'] = [
      '#type' => 'number',
      '#title' => $this->t('Max deletes per run'),
      '#description' => $this->t('Hard cap on deletions per sync cycle. If the delete list exceeds this, the sync logs a warning and bails out instead of mass-deleting. Protects against misconfigurations (e.g. a schedule being toggled off).'),
      '#default_value' => $config->get('sync.max_deletes_per_run') ?? 100,
      '#min' => 0,
      '#max' => 100000,
      '#step' => 10,
    ];

    $form['canceled'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Canceled sessions'),
      '#description' => $this->t('How to represent occurrences whose upstream status is <code>canceled</code>. Deleted occurrences are always removed.'),
      '#tree' => TRUE,
    ];
    $form['canceled']['title_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title prefix'),
      '#description' => $this->t('Prepended to the session title when the occurrence is canceled. Leave empty to keep the original title.'),
      '#default_value' => $config->get('sync.canceled_title_prefix') ?? 'CANCELED: ',
      '#size' => 40,
    ];
    $form['canceled']['publish_behavior'] = [
      '#type' => 'radios',
      '#title' => $this->t('Publish behavior'),
      '#options' => [
        'follow_api' => $this->t('Follow API <code>published</code> flag (canceled → typically unpublished)'),
        'always_unpublish' => $this->t('Always unpublish canceled sessions'),
        'keep_published' => $this->t('Keep canceled sessions published (show with prefix)'),
      ],
      '#default_value' => $config->get('sync.canceled_publish_behavior') ?? 'follow_api',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cancelValues = $form_state->getValue('canceled') ?? [];
    $syncValues = $form_state->getValue('sync') ?? [];
    $syncValues['canceled_title_prefix'] = $cancelValues['title_prefix'] ?? 'CANCELED: ';
    $syncValues['canceled_publish_behavior'] = $cancelValues['publish_behavior'] ?? 'follow_api';

    $this->config('yusaopeny_ymca360_instudio.settings')
      ->set('cron', $form_state->getValue('cron'))
      ->set('sync', $syncValues)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
