<?php

namespace Drupal\vspam\Form;

use Drupal\vbase\Form\ConfigTypedFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure site spam protection settings.
 */
class SpamProtectionSettings extends ConfigTypedFormBase {

  /**
   * {@inheritdoc}
   */
  protected $configName = 'vspam.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vspam_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this->configName);
    $definition = $this->definition($this->configName);

    $form['#attached']['library'][] = 'vspam/settings';

    $form['site_key'] = [
      '#title' => $this->t($definition['mapping']['site_key']['label']),
      '#type' => 'textfield',
      '#default_value' => $config->get('site_key'),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $form['secret_key'] = [
      '#title' => $this->t($definition['mapping']['secret_key']['label']),
      '#type' => 'textfield',
      '#default_value' => $config->get('secret_key'),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $form['hide_badge'] = [
      '#title' => $this->t($definition['mapping']['hide_badge']['label']),
      '#type' => 'checkbox',
      '#default_value' => $config->get('hide_badge'),
    ];

    $form['add_text'] = [
      '#title' => $this->t($definition['mapping']['add_text']['label']),
      '#type' => 'checkbox',
      '#default_value' => $config->get('add_text'),
    ];

    $form['forms'] = [
      '#title' => $this->t($definition['mapping']['forms']['label']),
      '#type' => 'fieldset',
      'help' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['form-item'],
        ],
        'actions' => [
          '#type' => 'container',
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'strong',
            '#value' => $this->t('Action'),
          ],
          'info' => [
            '#markup' => $this->t('may only contain alphanumeric characters and slashes.'),
          ],
        ],
        'score' => [
          '#type' => 'container',
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'strong',
            '#value' => $this->t('Interpreting the score:'),
          ],
          'info' => [
            '#markup' => $this->t('Google reCAPTCHA v3 returns a score (1.0 is very likely a good interaction, 0.0 is very likely a bot). Based on the score, you can take variable action in the context of your site.'),
          ],
        ],
      ],
      'forms' => [
        '#tree' => TRUE,
        '#type' => 'container',
        '#attributes' => [
          'id' => ['vspam-forms'],
        ],
      ],
      'actions' => [
        '#type' => 'actions',
        'add_more' => [
          '#type' => 'submit',
          '#value' => $this->t('Add more'),
          '#name' => 'add_more',
          '#validate' => ['::addMoreValidate'],
          '#submit' => ['::addMoreSubmit'],
          '#ajax' => [
            'callback' => '::ajaxCallback',
            'wrapper' => 'vspam-forms',
          ],
        ],
      ]
    ];

    $values = $form_state->getValue('forms', $config->get('forms'));

    foreach ($values as $value) {
      $form['forms']['forms'][] = $this->buildFormElement($value);
    }

    $form['forms']['forms'][] = $this->buildFormElement([]);

    return parent::buildForm($form, $form_state);
  }

  /**
   * Build form element.
   */
  public function buildFormElement(array $default_value) {
    $definition = $this->definition($this->configName);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => 'vspam-forms-grid'],
      'form_id' => [
        '#type' => 'textfield',
        '#title' => $this->t($definition['mapping']['forms']['sequence']['mapping']['form_id']['label']),
        '#default_value' => $default_value['form_id'] ?? '',
      ],
      'action' => [
        '#type' => 'textfield',
        '#title' => $this->t($definition['mapping']['forms']['sequence']['mapping']['action']['label']),
        '#default_value' => $default_value['action'] ?? '',
        '#placeholder' => $this->t('Same as Form ID'),
      ],
      'score' => [
        '#type' => 'number',
        '#title' => $this->t($definition['mapping']['forms']['sequence']['mapping']['score']['label']),
        '#min' => 0,
        '#max' => 1,
        '#step' => 0.1,
        '#default_value' => $default_value['score'] ?? 0.5,
      ],
    ];
  }

  /**
   * Ajax handler for the "add_more" button.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['forms']['forms'];
  }

  /**
   * Validate handler for the "add_more" button.
   */
  public function addMoreValidate(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Submit handler for the "add_more" button.
   */
  public function addMoreSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $forms = [];

    foreach ($form_state->getValue('forms') as $value) {
      if ($form_id = trim($value['form_id'])) {
        $forms[$form_id] = [
          'form_id' => $form_id,
          'action' => trim($value['action']),
          'score' => (float) $value['score'],
        ];
      }
    }

    $form_state->setValue('forms', $forms);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $clear = $this->config($this->configName)->get('site_key') != $form_state->getValue('site_key');

    parent::submitForm($form, $form_state);

    if ($clear) {
      \Drupal::service('library.discovery')->clearCachedDefinitions();
    }
  }

}
