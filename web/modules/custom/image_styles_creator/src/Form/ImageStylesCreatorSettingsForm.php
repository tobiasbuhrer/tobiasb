<?php

namespace Drupal\image_styles_creator\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ImageStylesCreatorSettingsForm.
 */
class ImageStylesCreatorSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'image_styles_creator.imagestylescreatorsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'image_styles_creator_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('image_styles_creator.imagestylescreatorsettings');
      $imageStyleOptions = image_style_options(FALSE);

      $ImageStyles = $config->get('image_styles');
      $form['image_styles'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Image styles'),
          '#description' => $this->t('Select image styles which will be created.'),
          '#options' => $imageStyleOptions,
          '#default_value' => !empty($ImageStyles) ? $ImageStyles : [],
          '#size' => 10,
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

    $this->config('image_styles_creator.imagestylescreatorsettings')
          ->set('image_styles', $form_state->getValue('image_styles'))
          ->save();
  }
}
