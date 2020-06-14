<?php

namespace Drupal\video_plupload_widget\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Component\Utility\Environment;



/**
 * Summary of UploadConfiguration
 */
class UploadConfiguration {
    public $file_extensions;
    public $max_filesize;
    public $cardinality;
}

/**
 * Plugin implementation of the 'video_plupload_widget' widget.
 *
 * @FieldWidget(
 *   id = "video_plupload_widget",
 *   label = @Translation("Video plupload widget"),
 *   field_types = {
 *     "video"
 *   }
 * )
 */
class VideoPluploadWidget extends FileWidget
{

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings()
    {
        $settings = array(
                'file_extensions' => 'mp4 ogv webm',
                'max_filesize' => '50 MB'
            ) + parent::defaultSettings();
        return $settings;
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state)
    {
        $element = parent::settingsForm($form, $form_state);
        $settings = $this->getSettings();
        // Make the extension list a little more human-friendly by comma-separation.
        $extensions = str_replace(' ', ', ', $settings['file_extensions']);
        $element['file_extensions'] = [
            '#type' => 'textfield',
            '#title' => t('Allowed file extensions'),
            '#default_value' => $extensions,
            '#description' => t('Separate extensions with a space or comma and do not include the leading dot.'),
            '#element_validate' => array(array(get_class($this), 'validateExtensions')),
            '#required' => TRUE,
            '#min' => 1,
        ];
        $element['max_filesize'] = [
            '#type' => 'textfield',
            '#title' => t('Maximum upload size'),
            '#default_value' => $settings['max_filesize'],
            '#description' => t('Enter a value like "512" (bytes), "80 KB" (kilobytes) or "50 MB" (megabytes) in order to restrict the allowed file size. If left empty the file sizes will be limited only by PHP\'s maximum post and file upload sizes (current limit <strong>%limit</strong>).', array('%limit' => format_size(Environment::getUploadMaxSize()))),
            '#size' => 10,
            '#element_validate' => array(array(get_class($this), 'validateMaxFilesize'))
        ];

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public function settingsSummary()
    {
        $settings = $this->getSettings();
        $summary = [];

        if (!empty($settings['file_extensions'])) {
            $summary[] = t('File extensions: @file_extensions', ['@file_extensions' => $settings['file_extensions']]);
        }

        if (!empty($settings['max_filesize'])) {
            $summary[] = t('Maximum filesize: @max_filesize', ['@max_filesize' => $settings['max_filesize']]);
        }
        return $summary;
    }

    /**
     * Form API callback.
     *
     * This function is assigned as an #element_validate callback in
     * settingsForm().
     *
     * This doubles as a convenience clean-up function and a validation routine.
     * Commas are allowed by the end-user, but ultimately the value will be stored
     * as a space-separated list for compatibility with file_validate_extensions().
     */
    public static function validateExtensions($element, FormStateInterface $form_state)
    {
        if (!empty($element['#value'])) {
            $extensions = preg_replace('/([, ]+\.?)/', ' ', trim(strtolower($element['#value'])));
            $extensions = array_filter(explode(' ', $extensions));
            $extensions = implode(' ', array_unique($extensions));
            if (!preg_match('/^([a-z0-9]+([.][a-z0-9])* ?)+$/', $extensions)) {
                $form_state->setError($element, t('The list of allowed extensions is not valid, be sure to exclude leading dots and to separate extensions with a comma or space.'));
            } else {
                $form_state->setValueForElement($element, $extensions);
            }
        }
    }

    /**
     * Form API callback.
     *
     * Ensures that a size has been entered and that it can be parsed by
     * \Drupal\Component\Utility\Bytes::toInt().
     *
     * This function is assigned as an #element_validate callback in
     * settingsForm().
     */
    public static function validateMaxFilesize($element, FormStateInterface $form_state)
    {
        if (!empty($element['#value']) && (Bytes::toInt($element['#value']) == 0)) {
            $form_state->setError($element, t('The option must contain a valid value. You may either leave the text field empty or enter a string like "512" (bytes), "80 KB" (kilobytes) or "50 MB" (megabytes).'));
        }
    }

    /**
     * Override to replace the upload/file HTML control
     * with the PLUPLOAD form element.
     *
     */
    public static function process($element, FormStateInterface $form_state, $form) {

        $element = parent::process($element, $form_state, $form);

        // If the form element does not have
        // an uplad control, skip this.
        if (!isset($element['upload'])) {
            return $element;
        }

        /** @var UploadConfiguration */
        $configuration = unserialize($form[$element['#parents'][0]]['#upload_configuration']);

        // Change the element description because
        // the PLUPLOAD widget MUST have the
        // extension filters as descripiton.
        // @see \Drupal\plupload\Element\PlUploadFile::preRenderPlUploadFile()
        // @see \Drupal\file\Plugin\Field\FieldWidget\FileWidget::formElement()
        $file_upload_help = array(
            '#theme' => 'file_upload_help',
            '#description' => '',
            '#upload_validators' => '',
            '#cardinality' => $configuration->cardinality,
        );
        $element['#description'] = \Drupal::service('renderer')->renderPlain($file_upload_help);

        // Replace the upload HTML element with PLUPLOAD
        // for a single file.
        $element['upload'] = [
            '#type' => 'plupload',
            '#title' => t('Upload files'),
            '#autoupload' => TRUE,
            '#autosubmit' => TRUE,
            '#submit_element' => "[name={$element['upload_button']['#name']}]",
            '#upload_validators' => [
                'file_validate_extensions' => array($configuration->file_extensions),
            ],
            '#plupload_settings' => [
                'max_file_size' => Bytes::toInt($configuration->max_file_size) . 'b',
            ],
            '#event_callbacks' => [
                'FilesAdded' => 'Drupal.video_plupload_widget.filesAddedCallback',
                'UploadComplete' => 'Drupal.video_plupload_widget.uploadCompleteCallback',
            ],
            '#attached' => [
                // We need to specify the plupload attachment because it is a default
                // and will be overriden by our value.
                'library' => ['video_plupload_widget/video_plupload_widget', 'plupload/plupload'],
            ]
        ];

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {

        $element = parent::form($items, $form, $form_state, $get_delta);
        $field_definition = $this->fieldDefinition->getFieldStorageDefinition();

        // Store these seetings once for the whole widget.
        $config = new UploadConfiguration();
        $config->file_extensions = $this->getSetting('file_extensions');
        $config->max_filesize = $this->getSetting('max_filesize');
        $config->cardinality = $field_definition->getCardinality();
        $element['#upload_configuration'] = serialize($config);

        return $element;
    }


    /**
     * Important!! The core FILE API relies on the value callback to save the managed file,
     * not the submit handler. The submit handler is only used for file deletions.
     */
    public static function value($element, $input = FALSE, FormStateInterface $form_state) {

        // We need to fake the element ID for the PlUploadFile form element
        // to work as expected as it is being nested in a form sub-element calle
        // upload.
        $id = $element['#id'];
        $id_backup = $id;

        // If a unique identifier added with '--', we need to exclude it
        if (preg_match('/(.*)(--[0-9A-Za-z-]+)$/', $id, $reg)) {
            $id = $reg[1];
        }

        // The form element is going to tell us if one
        // or more files where uploaded.
        $element['#id'] = $id . '-upload';
        $files = \Drupal\plupload\Element\PlUploadFile::valueCallback($element, $input, $form_state);
        $element['#id'] = $id_backup;
        if (empty($files)) {
            return parent::value($element, $input, $form_state);;
        }

        // During form rebuild after submit or ajax request this
        // method might be called twice, but we do not want to
        // generate the file entities twice....

        // This files are RAW files, they are not registered
        // anywhere, so won't get deleted on CRON runs :(
        //$file = reset($files);

        $currentmonth = "://" . date("Y-m");
        // Create target directory if necessary.
        $destination = \Drupal::config('system.file')
                ->get('default_scheme') . $currentmonth;
        
        \Drupal::service('file_system')->prepareDirectory($destination, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS );

        foreach ($files as $uploaded_file) {
            $file_uri = \Drupal::service('stream_wrapper_manager')->normalizeUri($destination . '/' . $uploaded_file['name']);

            // Create file object from a locally copied file.
            //$uri = file_unmanaged_copy($uploaded_file['tmppath'], $file_uri, FILE_EXISTS_REPLACE);
            $uri = \Drupal::service('file_system')->copy($uploaded_file['tmppath'], $file_uri, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
            
            $f = File::Create([
                'uri' => $uri,
                'uid' => \Drupal::currentUser()->id(),
                'status' => 0,
                'filename' => $uploaded_file['name'],
                'filemime' => \Drupal::service('file.mime_type.guesser')->guess($destination),
            ]);
            $f->save();
            $return['fids'][] = $f->id();
        }
        return $return;
    }


}
