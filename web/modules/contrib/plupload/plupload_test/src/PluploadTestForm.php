<?php

namespace Drupal\plupload_test;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Plupload test form class.
 */
class PluploadTestForm implements FormInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return '_plupload_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['plupload'] = [
      '#type' => 'plupload',
      '#title' => 'Plupload',
      '#upload_validators' => [
        'file_validate_extensions' => ['zip'],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('plupload') as $uploaded_file) {
      if ($uploaded_file['status'] != 'done') {
        $form_state->setErrorByName('plupload', $this->t("Upload of %filename failed.", ['%filename' => $uploaded_file['name']]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Create target directory if necessary.
    $destination = \Drupal::config('system.file')
      ->get('default_scheme') . '://plupload-test';
    \Drupal::service('file_system')->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $saved_files = [];

    foreach ($form_state->getValue('plupload') as $uploaded_file) {

      $file_uri = $this->loadStreamWrapper()->normalizeUri($destination . '/' . $uploaded_file['name']);

      // Move file without creating a new 'file' entity.
      \Drupal::service('file_system')->move($uploaded_file['tmppath'], $file_uri);

      // @todo: When https://www.drupal.org/node/2245927 is resolved,
      // use a helper to save file to file_managed table
      $saved_files[] = $file_uri;
    }
    if (!empty($saved_files)) {
      \Drupal::messenger()->addStatus('Files uploaded correctly: ' . implode(', ', $saved_files) . '.');
    }
  }

  /**
   * Returns the Drupal stream wrapper manager service.
   */
  private function loadStreamWrapper() {
    return \Drupal::service('stream_wrapper_manager');
  }

}
