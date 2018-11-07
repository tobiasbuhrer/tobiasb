<?php

namespace Drupal\image_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Entity\EntityInterface;
use Drupal\file\FileInterface;

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

                $msg .= '<strong>' . $fields[$thisfield]->getLabel() . '</strong> ' . $this->t('will get value from IPTC/EXIF field') . ': <strong>' . $config->get($key) . '</strong><br />';
            }
        }
        $msg .= '</p>';
        \Drupal::messenger()->addMessage($this->t('Remember to set any default values you want in all created nodes'), 'warning');
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
        //we use the hour to avoid problems with duplicate file names.
        $currentmonth = "://" . date("Y-m-d-H");
        $file_usage = \Drupal::service('file.usage');
        // Create target directory if necessary.
        $destination = \Drupal::config('system.file')
                ->get('default_scheme') . $currentmonth;
        file_prepare_directory($destination, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
        $styles = ImageStyle::loadMultiple();

        //fields of target node type
        $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $config->get('contenttype'));

        foreach ($form_state->getValue('plupload') as $uploaded_file) {

            $file_uri = file_stream_wrapper_uri_normalize($destination . '/' . $uploaded_file['name']);

            // Create file object from a locally copied file.
            $uri = file_unmanaged_copy($uploaded_file['tmppath'], $file_uri, FILE_EXISTS_REPLACE);
            $file = File::Create([
                'uid' => 1,
                'uri' => $uri,
                'status' => 1,
            ]);
            $file->save();

            //use filename as default title
            $title = $file->getFilename();

            //load metatags for image
            $uri = $file->GetFileUri();

            $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager')->getViaUri($uri);
            $filepath = $stream_wrapper_manager->realpath();
            //$filepath = \Drupal::service('file_system')->realpath($uri);
            $metatags = ImageImportSettingsForm::readMetaTags($filepath);

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

                    switch ($fields[$fieldname]->getType()) {
                        case "string":
                        case "list_string":
                        case "string_long":
                            $v = (string)$metatags[$mapping];
                            //we don't write empty values. If no value is written, default will be applied.
                            if (!empty($v)) {
                                $newnode[$fieldname] = (string)$metatags[$mapping];

                            }
                            break;
                        case "datetime":
                            $datetime = date_create_from_format('Y:m:d H:i:s', $metatags[$mapping]);

                            //we don't write empty values. If no value is written, default will be applied.
                            if (!empty($datetime)) {
                                $newnode[$fieldname] = date_format($datetime, 'Y-m-d H:i:s');
                            }
                            break;
                        case "geolocation":
                        case "geofield":
                            if (substr($mapping, 0, 3) == 'GPS') {
                                $Latitude = $metatags['GPSLatitude'];
                                $Longitude = $metatags['GPSLongitude'];
                            }
                            if ((!empty($Latitude)) and
                                (!empty($Longitude))) {

                                if ($fields[$fieldname]->getType() == "geolocation") {
                                    $newnode[$fieldname]['lat'] = $Latitude;
                                    $newnode[$fieldname]['lng'] = $Longitude;
                                } else {
                                    // geofield - Generate a point [lon, lat]
                                    $coord = [$Longitude, $Latitude];
                                    $point = \Drupal::service('geofield.wkt_generator')->wktBuildPoint($coord);
                                    $newnode[$fieldname] = $point;
                                }
                                break;
                            };
                    }
                }
            }

            $node = Node::create($newnode);
            $node->save();
            $file_usage->add($file, 'node', 'node', $node->id());
            $file->save();

            //create image styles

            $fid = $file->id();
            $entity = \Drupal\file\Entity\File::load($fid);

            if ($entity instanceof FileInterface) {
                $image = \Drupal::service('image.factory')->get($entity->getFileUri());
                /** @var \Drupal\Core\Image\Image $image */
                if ($image->isValid()) {
                    $queue = \Drupal::queue('image_import_image_style');
                    $data = ['entity' => $entity];
                    $queue->createItem($data);
                }
            }
        }
        parent::submitForm($form, $form_state);
    }

    // Convert a lat or lon 3-array to decimal degrees
    function convertToDegree($exifcoordinates)
    {
        $values = explode(',', $exifcoordinates);
        $deg = explode('/', $values[0]);
        $degrees = $deg[0] / $deg[1];
        $min = explode('/', $values[1]);
        $minutes = $min[0] / $min[1];
        $sec = explode('/', $values[2]);
        $seconds = $sec[0] / $sec[1];
        return $degrees + $minutes / 60.0 + $seconds / 3600;
    }

}
