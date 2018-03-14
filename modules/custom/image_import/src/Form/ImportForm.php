<?php

namespace Drupal\image_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ImportForm.
 */
class ImportForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'image_import.import',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('image_import.import');
    $form['upload_image_s'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Upload image(s)'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('upload_image_s'),
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

    $this->config('image_import.import')
      ->set('upload_image_s', $form_state->getValue('upload_image_s'))
      ->save();
  }

}
