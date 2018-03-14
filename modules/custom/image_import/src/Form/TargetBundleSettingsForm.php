<?php

namespace Drupal\image_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class TargetBundleSettingsForm.
 */
class TargetBundleSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'image_import.targetbundlesettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'target_bundle_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('image_import.targetbundlesettings');
    $form['choose_target_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose target bundle:'),
      '#options' => ['one' => $this->t('one'), 'two' => $this->t('two')],
      '#size' => 5,
      '#default_value' => $config->get('choose_target_bundle'),
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
    parent::submitForm($form, $form_state);

    $this->config('image_import.targetbundlesettings')
      ->set('choose_target_bundle', $form_state->getValue('choose_target_bundle'))
      ->save();
  }

}
