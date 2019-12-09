<?php

namespace Drupal\image_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

/**
 * Class ImageImportSettingsForm.
 */
class ImageImportSettingsForm extends ConfigFormBase
{
    /**
     * The entity manager.
     *
     * @var \Drupal\Core\Entity\EntityManagerInterface
     */
    protected $entityTypeManager;

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
     * Constructs the ImgImportettingsForm object.
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
    public function getFormId()
    {
        return 'image_import_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('image_import.imageimportsettings');
        $target_node_type = $this->entityTypeManager->getStorage('node_type')->load($config->get('contenttype'));
        $msg = '<p>' . $this->t('Images shall be imported to Node type') . ': <strong>' . $target_node_type->label() . '</strong><br />';

        //can we show dependent form elements?
        $form['mapping'] = array(
            '#prefix' => $msg,
            '#type' => 'fieldset',
            '#title' => $this
                ->t('Map fields to IPTC/EXIF values'),
        );
        $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $config->get('contenttype'));

        $options = array();
        foreach ($fields as $field) {
            if (($field->getFieldStorageDefinition()->isBaseField() == FALSE) and ($field->getType() == "image")) {
                $options[$field->getName()] = $field->getLabel();
            }
        }
        $form['mapping']['image_field'] = array(
            '#type' => 'select',
            '#title' => $this->t('Source Image Field'),
            '#default_value' => $config->get('image_field'),
            '#required' => TRUE,
            '#options' => $options,
        );

        $filepath = drupal_get_path('module', 'image_import') . '/sample.jpg';
        $tags = $this->readMetaTags($filepath);

        $tagoptions = array();
        foreach ($tags as $key => $tag) {
            $tagoptions[$key] = $key;
        }

        foreach ($fields as $field) {
            if (($field->getFieldStorageDefinition()->isBaseField() == FALSE) or ($field->getName() == "title")) {
                $type = $field->getType();
                if (in_array($type, array('string', 'string_long', 'list_string', 'geolocation', 'geofield', 'datetime'))) {
                    $label = $field->getName();
                    $name = $field->getLabel();
                    $form['mapping'][$label] = array(
                        '#type' => 'select',
                        '#title' => $name . ' (' . $this->t('Type') . ': ' . $type . ')',
                        '#default_value' => $config->get('exif_' . $label),
                        '#empty_value' => '',
                        '#required' => FALSE,
                        '#options' => $tagoptions,
                    );
                }
            }
        }
        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $this->config('image_import.imageimportsettings')
            ->set('image_field', $form_state->getValue('image_field'))
            ->save();

        $formvalues = $form_state->getValues();

        foreach ($formvalues as $key => $value) {
            if (($key == 'title') or (substr($key, 0, 6) == 'field_')) {
                if ($value == '') {
                    $this->config('image_import.imageimportsettings')
                        ->clear('exif_' . $key)
                        ->save();
                } else {
                    $this->config('image_import.imageimportsettings')
                        ->set('exif_' . $key, $value)
                        ->save();
                }
            }
        }
        parent::submitForm($form, $form_state);
    }

    public static function readMetaTags($uri)
    {
        $fields = array();
        $exiftoolpath = \Drupal::config('image_import.imageimportsettings')->get('exiftoolpath');

        //Get all of the EXIF tags
        //$exif = exif_read_data($uri, NULL, TRUE);
        $exiftoolConfig = new \ExiftoolReader\Config\Exiftool();
        $exiftoolConfig->setConfig([
            'path'    => $exiftoolpath,
            'command' => 'exiftool -j -n %s',
        ]);
        $command = new \ExiftoolReader\Command($exiftoolConfig);
        $reader = new \ExiftoolReader\Reader($command);

        $output = $reader->read($uri)->getDecoded();

        foreach ($output as $key => $value) {
            $fields[$key] = $value;
        }
        return $fields;
    }
}
