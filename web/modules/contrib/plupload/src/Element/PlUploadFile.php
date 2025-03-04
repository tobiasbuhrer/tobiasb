<?php

namespace Drupal\plupload\Element;

use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;

/**
 * Provides a PLUpload widget for uploading and saving files.
 *
 * @see ManagedFile::getInfo()
 *
 * @FormElement("plupload")
 */
class PlUploadFile extends FormElementBase {

  /**
   * {@inheritdoc}
   *
   * Note: based on plupload_element_info().
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#attributes' => ['class' => ['plupload-element']],
      '#theme_wrappers' => ['form_element'],
      '#theme' => 'container',
      '#attached' => [
        'library' => ['plupload/plupload'],
      ],
      '#process' => [
        [$class, 'processPlUploadFile'],
      ],
      '#element_validate' => [
        [$class, 'validatePlUploadFile'],
      ],
      '#pre_render' => [
        [$class, 'preRenderPlUploadFile'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @see ManagedFile::valueCallback
   * @see file_managed_file_save_upload()
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');

    $id = $element['#id'];
    // Exclude unique identifier added with '--' in Ajax requests.
    if (preg_match('/(.*?)(--[0-9A-Za-z_-]+)$/', $id, $reg)) {
      $id = $reg[1];
    }

    // Seems cleaner to use something like this, but it's empty.
    // $request_files = \Drupal::request()->files;
    $input = $form_state->getUserInput();

    $files = [];
    foreach ($input as $key => $value) {
      if (preg_match('/' . $id . '(--([0-9A-Za-z_-]+))?_([0-9]+)_(tmpname|name|status)/', $key, $reg)) {
        $i = $reg[3];
        $key = $reg[4];

        // Only add the keys we expect.
        if (!in_array($key, ['tmpname', 'name', 'status'])) {
          continue;
        }

        // Munge the submitted file names for security.
        //
        // Similar munging is normally done by file_save_upload(), but submit
        // handlers for forms containing plupload elements can't use
        // file_save_upload(), for reasons discussed in plupload_test_submit().
        // So we have to do this for them.
        //
        // Note that we do the munging here in the value callback function
        // (rather than during form validation or elsewhere) because we want to
        // actually modify the submitted values rather than reject them
        // outright; file names that require munging can be innocent and do
        // not necessarily indicate an attempted exploit. Actual validation of
        // the file names is performed later, in plupload_element_validate().
        if (in_array($key, ['tmpname', 'name'])) {
          // Find the whitelist of extensions to use when munging. If there are
          // none, we'll be adding default ones in plupload_element_process(),
          // so use those here.
          if (isset($element['#upload_validators']['FileExtension']['extensions'])) {
            $extensions = $element['#upload_validators']['FileExtension']['extensions'];
          }
          else {
            $validators = _plupload_default_upload_validators();
            $extensions = $validators['FileExtension']['extensions'];
          }

          $event = new FileUploadSanitizeNameEvent($value, $extensions);
          $event_dispatcher->dispatch($event);
          $value = $event->getFilename();

          // To prevent directory traversal issues, make sure the file name does
          // not contain any directory components in it. (This more properly
          // belongs in the form validation step, but it's simpler to do here so
          // that we don't have to deal with the temporary file names during
          // form validation and can just focus on the final file name.)
          //
          // This step is necessary since this module allows a large amount of
          // flexibility in where its files are placed (for example, they could
          // be intended for public://subdirectory rather than public://, and we
          // don't want an attacker to be able to get them back into the top
          // level of public:// in that case).
          $value = rtrim(\Drupal::service('file_system')->basename($value), '.');

          // Based on the same feture from file_save_upload().
          if (!\Drupal::config('system.file')->get('allow_insecure_uploads') && preg_match('/\.(php|pl|py|cgi|asp|js)(\.|$)/i', $value) && (substr($value, -4) != '.txt')) {
            $value .= '.txt';

            // The .txt extension may not be in the allowed list of extensions.
            // We have to add it here or else the file upload will fail.
            if (!empty($extensions)) {
              $element['#upload_validators']['FileExtension']['extensions'] .= ' txt';
              \Drupal::messenger()->addMessage(t('For security reasons, your upload has been renamed to %filename.', ['%filename' => $value]));
            }
          }
        }

        // The temporary file name has to be processed further so it matches
        // what was used when the file was written; see
        // plupload_handle_uploads().
        if ($key == 'tmpname') {
          $value = _plupload_fix_temporary_filename($value);
          // We also define an extra key 'tmppath' which is useful so that
          // submit handlers do not need to know which directory plupload
          // stored the temporary files in before trying to copy them.
          $files[$i]['tmppath'] = \Drupal::config('plupload.settings')->get('temporary_uri') . $value;
        }
        elseif ($key == 'name') {
          $value = \Drupal::service('transliteration')->transliterate($value);
        }

        // Store the final value in the array we will return.
        $files[$i][$key] = $value;
      }
    }
    return $files;
  }

  /**
   * Render API callback: Expands the managed_file element type.
   *
   * Expands the file type to include Upload and Remove buttons, as well as
   * support for a default value.
   *
   * Note: based on plupload_element_process().
   */
  public static function processPlUploadFile(&$element, FormStateInterface $form_state, &$complete_form) {
    // Start session if not there yet. We need session if we want security
    // tokens to work properly.
    $session_manager = \Drupal::service('session_manager');
    if (!$session_manager->isStarted()) {
      $session_manager->start();
    }

    if (!isset($element['#upload_validators'])) {
      $element['#upload_validators'] = [];
    }
    $element['#upload_validators'] += _plupload_default_upload_validators();
    return $element;
  }

  /**
   * Render API callback: Hides display of the upload or remove controls.
   *
   * Upload controls are hidden when a file is already uploaded. Remove controls
   * are hidden when there is no file attached. Controls are hidden here instead
   * of in \Drupal\file\Element\ManagedFile::processManagedFile(), because
   * #access for these buttons depends on the managed_file element's #value. See
   * the documentation of \Drupal\Core\Form\FormBuilderInterface::doBuildForm()
   * for more detailed information about the relationship between #process,
   * #value, and #access.
   *
   * Because #access is set here, it affects display only and does not prevent
   * JavaScript or other untrusted code from submitting the form as though
   * access were enabled. The form processing functions for these elements
   * should not assume that the buttons can't be "clicked" just because they are
   * not displayed.
   *
   * @see \Drupal\file\Element\ManagedFile::processManagedFile()
   * @see \Drupal\Core\Form\FormBuilderInterface::doBuildForm()
   *
   * Note: based on plupload_element_pre_render().
   */
  public static function preRenderPlUploadFile($element) {
    $settings = $element['#plupload_settings'] ?? [];

    // Set upload URL.
    if (empty($settings['url'])) {
      $settings['url'] = Url::fromRoute('plupload.upload', [], [
        'query' => ['token' => \Drupal::csrfToken()->get('plupload-handle-uploads')],
      ])->toString();
    }

    // The Plupload library supports client-side validation of file extension,
    // so pass along the information for it to do that. However, as with all
    // client- side validation, this is a UI enhancement only, and not a
    // replacement for server-side validation.
    if (empty($settings['filters']) && isset($element['#upload_validators']['FileExtension']['extensions'])) {
      $settings['filters'][] = [
        // @todo Some runtimes (e.g., flash) require a non-empty title for each
        //   filter, but I don't know what this title is used for. Seems a shame
        //   to hard-code it, but what's a good way to avoid that?
        'title' => t('Allowed files'),
        'extensions' => str_replace(' ', ',', $element['#upload_validators']['FileExtension']['extensions']),
      ];
    }
    // Check for autoupload and autosubmit settings and add appropriate
    // callback.
    if (!empty($element['#autoupload'])) {
      $settings['init']['FilesAdded'] = 'Drupal.plupload.filesAddedCallback';
      if (!empty($element['#autosubmit'])) {
        $settings['init']['UploadComplete'] = 'Drupal.plupload.uploadCompleteCallback';
      }
    }
    // Add a specific submit element that we want to click if one is specified.
    if (!empty($element['#submit_element'])) {
      $settings['submit_element'] = $element['#submit_element'];
    }
    // Check if there are event callbacks & append them to current ones, if any.
    if (!empty($element['#event_callbacks'])) {
      // array_merge() only accepts parameters of type array.
      if (!isset($settings['init'])) {
        $settings['init'] = [];
      }
      $settings['init'] = array_merge($settings['init'], $element['#event_callbacks']);
    }

    if (empty($element['#description'])) {
      $element['#description'] = '';
    }
    $element['#description'] = [
      '#theme' => 'file_upload_help',
      '#description' => $element['#description'],
      '#upload_validators' => $element['#upload_validators'],
    ];

    // Global settings.
    $library_discovery = \Drupal::service('library.discovery');
    $library = $library_discovery->getLibraryByName('plupload', 'plupload');

    $element['#attached']['drupalSettings']['plupload'] = [
      '_default' => $library['settings']['plupload']['_default'],
      $element['#id'] => $settings,
    ];

    return $element;
  }

  /**
   * Render API callback: Validates the managed_file element.
   *
   * Note: based on plupload_element_validate().
   */
  public static function validatePlUploadFile(&$element, FormStateInterface $form_state, &$complete_form) {

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');

    /** @var \Symfony\Component\Mime\MimeTypeGuesserInterface $guesser */
    $guesser = \Drupal::service('file.mime_type.guesser');

    /** @var \Drupal\file\Validation\FileValidatorInterface $file_validator */
    $file_validator = \Drupal::service('file.validator');

    foreach ($element['#value'] as $file_info) {
      // Here we create a $file object for a file that doesn't exist yet,
      // because saving the file to its destination is done in a submit handler.
      // Using tmp path will give validators access to the actual file on disk
      // and filesize information. We manually modify filename and mime to allow
      // extension checks.
      $destination = \Drupal::config('system.file')->get('default_scheme') . '://' . $file_info['name'];
      $file = File::create([
        'uri' => $file_info['tmppath'],
        'uid' => \Drupal::currentUser()->id(),
        'filename' => $file_system->basename($destination),
        'filemime' => $guesser->guessMimeType($destination),
        'filesize' => filesize($file_info['tmppath']),
      ]);

      $violations = $file_validator->validate($file, $element['#upload_validators']);
      if ($violations->count() > 0) {
        $errors = [t('The specified file %name could not be uploaded.', ['%name' => $file->getFilename()])];
        foreach ($violations as $violation) {
          $errors[] = $violation->getMessage();
        }
        $form_state->setError($element, Markup::create(implode(' ', $errors)));
      }
    }
  }

}
