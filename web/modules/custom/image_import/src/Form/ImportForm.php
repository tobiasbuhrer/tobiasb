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
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper;
use Drupal\Core\Datetime\DrupalDateTime;

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
        \Drupal::service('file_system')->prepareDirectory($destination, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS );
        $styles = ImageStyle::loadMultiple();

        //fields of target node type
        $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $config->get('contenttype'));

        foreach ($form_state->getValue('plupload') as $uploaded_file) {

	    $file_uri = \Drupal::service('stream_wrapper_manager')->normalizeUri($destination . '/' . $uploaded_file['name']);

            // Create file object from a locally copied file.
            $uri = \Drupal::service('file_system')->copy($uploaded_file['tmppath'], $file_uri, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);

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
            if (isset($metatags[$config->get('exif_title')]))
            {
              $test_title = $metatags[$config->get('exif_title')];

                //do convert to UTF8, otherwise we may have problems
                if (is_array($test_title)) {
                  //all IPTC values are arrays
                  $new_title = $this->encodeToUtf8($test_title[0]);
                }
                else {
                  //could be an EXIF string
                  $new_title = $this->encodeToUtf8($test_title);
                };
                if (strlen($new_title) > 0) {
                  $title = $new_title;
                };
              };

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
                          //we don't write empty values. If no value is written, default will be applied.
                          //IPTC values are always arrays, exif values not necessarily so
                          if (isset($metatags[$mapping])) {
                            $value = $metatags[$mapping];
                            //do convert to UTF8, otherwise we may have problems
                            if (is_array($value)) {
                              //all IPTC values are arrays
                            $newnode[$fieldname] = $this->encodeToUtf8($value[0]);
                            }
                            else {
                              //could be an EXIF string
                              $newnode[$fieldname] = $this->encodeToUtf8($value);
                            }
                          };
                          break;
                        case "datetime":
                          //we don't write empty values. If no value is written, default will be applied.
                          if (isset($metatags[$mapping])) {
                            $datetime = date_create_from_format('Y:m:d H:i:s', $metatags[$mapping]);
                            $date = DrupalDateTime::createFromDateTime($datetime);
                            $date->setTimezone(new \DateTimeZone('UTC'));
                            $newnode[$fieldname] = $date->format('Y-m-d\TH:i:s');
                            };
                            break;
                        case "geolocation":
                      case "geofield":
                            if ((substr($mapping, 0, 3) == 'GPS') and isset($metatags["GPS-GPSLongitude"]) and isset($metatags["GPS-GPSLatitude"])) {
                                //we ignore any mapping, put to the GPS value in the file
                                //we have to convert this to digital coordinates
                                $Longitude = $this->getGps($metatags["GPS-GPSLongitude"], $metatags['GPS-GPSLongitudeRef']);
                                $Latitude = $this->getGps($metatags["GPS-GPSLatitude"], $metatags['GPS-GPSLatitudeRef']);

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
                            }
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

    private function getGps($exifCoord, $hemi) {
        $degrees = count($exifCoord) > 0 ? $this->gps2Num($exifCoord[0]) : 0;
        $minutes = count($exifCoord) > 1 ? $this->gps2Num($exifCoord[1]) : 0;
        $seconds = count($exifCoord) > 2 ? $this->gps2Num($exifCoord[2]) : 0;

        $flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;

        return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
    }

    private function gps2Num($coordPart) {
        $parts = explode('/', $coordPart);
        if (count($parts) <= 0)
          return 0;
        if (count($parts) == 1)
          return $parts[0];
        return floatval($parts[0]) / floatval($parts[1]);
    }

  private function encodeToUtf8($string) {
    return mb_convert_encoding($string, "UTF-8", mb_detect_encoding($string, "UTF-8, ISO-8859-1, ISO-8859-15", true));
  }

}
