<?php

namespace Drupal\image_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

/**
 * Class ImportForm.
 */
class ImportForm extends ConfigFormBase
{
    /**
     * Constructs the ConfigFormBase object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity manager.
     */
    public function __construct(EntityTypeManagerInterface $entity_type_manager)
    {
        $this->entityTypeManager = $entity_type_manager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('entity.manager')
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [
            'image_import.imageimportsettings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'import_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('image_import.imageimportsettings');
        $target_node_type = $this->entityTypeManager->getStorage('node_type')->load($config->get('contenttype'));
        $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $config->get('contenttype'));
        $msg = '<p>' . $this->t('Images shall be imported to Node type') . ': <strong>' . $target_node_type->label() . '</strong><br />';
        $msg .= $this->t('The image field will be') . ': <strong>' . $fields[$config->get('image_field')]->getLabel() . '</strong></p><p>';

        $configs = $config->get();
        foreach ($configs as $key => $setting) {

            if (substr($key, 0, 5) == 'exif_') {
                //$msg .= $key . '<br />';
                $thisfield = substr($key, 5);

                $msg .= '<strong>' . $fields[$thisfield]->getLabel() . '</strong> ' . $this->t('will get value from IPTC/EXIF field') . ': <strong>' . ImageImportSettingsForm::getHumanReadableKey($config->get($key)) . '</strong><br />';
            }
        }
        $msg .= '</p>';
        drupal_set_message($this->t('Remember to set any default values you want in all created nodes'), 'warning');
        $form['plupload'] = array(
            '#prefix' => $msg,
            '#type' => 'plupload',
            '#title' => 'Image(s) to upload',
            '#upload_validators' => array(
                'file_validate_extensions' => array('jpg jpeg gif png'),
            ),
        );

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateForm($form, $form_state);

        if (count($form_state->getValue('plupload')) == 0) {
            $form_state->setErrorByName('plupload', $this->t("No image chosen"));
        }

        foreach ($form_state->getValue('plupload') as $uploaded_file) {
            if ($uploaded_file['status'] != 'done') {
                $form_state->setErrorByName('plupload', $this->t("Upload of %filename failed.", array('%filename' => $uploaded_file['name'])));
            }
        }

        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $config = $this->config('image_import.imageimportsettings');
        $currentmonth = "://" . date("Y-m");
        // Create target directory if necessary.
        $destination = \Drupal::config('system.file')
                ->get('default_scheme') . $currentmonth;
        file_prepare_directory($destination, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
        //to delete
        $saved_files = array();

        //fields of target node type
        $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $config->get('contenttype'));

        foreach ($form_state->getValue('plupload') as $uploaded_file) {

            $file_uri = file_stream_wrapper_uri_normalize($destination . '/' . $uploaded_file['name']);

            // Create file object from a locally copied file.
            $uri = file_unmanaged_copy($uploaded_file['tmppath'], $file_uri, FILE_EXISTS_REPLACE);
            $file = File::Create([
                'uri' => $uri,
            ]);
            $file->save();

            //only for success message... to remove
            //$saved_files[] = $file->getFileUri();
            // Create node object with attached file.

            //use filename as default title
            $title = $file->getFilename();

            //load metatags for image
            $filepath = file_create_url($file->getFileUri());
            //$saved_files[] = $filepath;

            $metatags = ImageImportSettingsForm::readMetaTags($filepath, TRUE);

            //title: if mapping is set, result needs to have at least one char
            if (($config->get('exif_title')) and (strlen($metatags[$config->get('exif_title')]) > 0)) {
                $title = $metatags[$config->get('exif_title')];
            }

            $newnode = array();
            $newnode['type'] = $config->get('contenttype');
            $newnode['title'] = $title;
            $newnode[$config->get('image_field')]['target_id'] = $file->id();
            $newnode[$config->get('image_field')]['alt'] = $title;

            $configs = $config->get();
            foreach ($configs as $key => $mapping) {
                if ((substr($key, 0, 5) == 'exif_') and ($key !== 'exif_title')) {
                    $fieldname = substr($key, 5);
                    //$saved_files[] = $filepath . ' ' . $fieldname . '=' . $fields[$fieldname]->getName();

                    switch ($fields[$fieldname]->getType()) {
                        case "string":
                        case "list_string":
                            $newnode[$fieldname] = (string)$metatags[$mapping];
                            break;
                        case "datetime":
                            $datetime = date_create_from_format('Y:m:d H:i:s', $metatags[$mapping]);
                            $newnode[$fieldname] = date_format($datetime, 'Y-m-d\TH:i:s');
                            break;
                        case "geolocation":
                            if (substr($mapping, 0, 9) == 'EXIF:GPS:') {
                                $lat = $metatags['EXIF:GPS:GPSLatitude'];
                                $lon = $metatags['EXIF:GPS:GPSLongitude'];
                                $latref = $metatags['EXIF:GPS:GPSLatitudeRef'];
                                $lonref = $metatags['EXIF:GPS:GPSLongitudeRef'];
                            }
                            if ((!empty($lat)) and
                                (!empty($lon)) and
                                (!empty($latref)) and
                                (!empty($lonref))) {

                                if ($latref == "N") {
                                    $Latitude = $this->convertToDegree($lat);
                                } else {
                                    $Latitude = 0 - $this->convertToDegree($lat);
                                }

                                if ($lonref == "E") {
                                    $Longitude = $this->convertToDegree($lon);
                                } else {
                                    $Longitude = 0 - $this->convertToDegree($lon);
                                }
                                $newnode[$fieldname]['lat'] = $Latitude;
                                $newnode[$fieldname]['lng'] = $Longitude;
                            }
                            break;
                    };
                }
            }

            $node = Node::create($newnode);
            $node->save();
            $nodeid = $node->id();

        }
        if (!empty($saved_files)) {
            drupal_set_message('Files uploaded correctly: ' . implode(', ', $saved_files) . '.', 'status');
        }
        parent::submitForm($form, $form_state);
    }

    // Convert a lat or lon 3-array to decimal degrees
    private function convertToDegree($exifcoordinates) {
        $values = explode(',',$exifcoordinates);
        $deg = explode('/',$values[0]);
        $degrees = $deg[0]/$deg[1];
        $min = explode('/',$values[1]);
        $minutes = $min[0]/$min[1];
        $sec = explode('/',$values[2]);
        $seconds  = $sec[0]/$sec[1];
        return $degrees + $minutes / 60.0 + $seconds / 3600;
    }

}
