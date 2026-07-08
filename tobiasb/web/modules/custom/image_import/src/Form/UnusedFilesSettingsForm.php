<?php

namespace Drupal\image_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class UnusedFilesSettingsForm.
 */
class UnusedFilesSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'file.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'unused_files_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('file.settings');

    $form['is_temporary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Click to set unused files to temporary'),
      '#default_value' => $config->get('make_unused_managed_files_temporary'),
      '#return_value' => true,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('file.settings')
      ->set('make_unused_managed_files_temporary', $form_state->getValue('is_temporary'))
      ->save();
      parent::submitForm($form, $form_state);
  }

}
