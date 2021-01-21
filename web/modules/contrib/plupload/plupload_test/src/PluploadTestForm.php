<?php

namespace Drupal\plupload_test;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Plupload test form class.
 */
class PluploadTestForm implements FormInterface {

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
    $form['plupload'] = array(
      '#type' => 'plupload',
      '#title' => 'Plupload',
      '#upload_validators' => array(
        'file_validate_extensions' => array('zip'),
      ),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('plupload') as $uploaded_file) {
      if ($uploaded_file['status'] != 'done') {
        $form_state->setErrorByName('plupload', t("Upload of %filename failed.", array('%filename' => $uploaded_file['name'])));
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

    $saved_files = array();

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
