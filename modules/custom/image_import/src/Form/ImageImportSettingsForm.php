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
            $tagoptions[$key] = $this->getHumanReadableKey($key);
        }

        foreach ($fields as $field) {
            if (($field->getFieldStorageDefinition()->isBaseField() == FALSE) or ($field->getName() == "title")) {
                $type = $field->getType();
                if (in_array($type, array('string', 'list_string', 'geolocation', 'geofield', 'datetime'))) {
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

    public static function readMetaTags($uri, $concatenate_arrays = TRUE)
    {
        $fields = array();

        //Get all of the EXIF tags
        $exif = exif_read_data($uri, NULL, TRUE);
        if (is_array($exif)) {
            foreach ($exif as $name => $section) {
                foreach ($section as $key => $value) {
                    if ($concatenate_arrays && is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    $fields['EXIF:' . $name . ':' . $key] = ImageImportSettingsForm::check_plain($value);
                }
            }
        }

        //XMP - test
        $fields = array_merge($fields, ImageImportSettingsForm::get_xmp($uri));

        //Look for IPTC data
        $size = getimagesize($uri, $info);

        if (is_array($info)) {
            foreach ($info as $block) {
                $iptc = iptcparse($block);

                if ($iptc) {
                    //IPTC:2#254 can contain name=value pairs
                    if (isset($iptc['2#254']) && is_array($iptc['2#254'])) {
                        $i = 0;
                        foreach ($iptc['2#254'] as $iptc_field) {
                            $subfields = explode('=', $iptc_field);
                            $iptc['2#254.' . $subfields[0]] = $subfields[1];
                        }
                        unset($iptc['2#254']);
                    }
                    foreach ($iptc as $key => $value) {
                        if ($concatenate_arrays && is_array($value)) {
                            $value = implode(', ', $value);
                        }
                        $fields['IPTC:' . $key] = ImageImportSettingsForm::check_plain($value);
                    }
                }
            }
        }

        //TODO: add special treatment for geofield gps import

        if (!is_array($exif) && !isset($iptc)) {
            return FALSE;
        }
        return $fields;
    }

    public static function check_plain($text)
    {
        if (is_null($text)) {
            $text = "";
        }
        if (!mb_detect_encoding($text, 'UTF-8', true)) {
            $text = str_replace("&lt;br /&gt;", "\n",
                str_replace("<", "&lt;",
                    str_replace(">", "&gt;",
                        mb_convert_encoding(html_entity_decode($text), "UTF-8", "ISO-8859-1"))));
        }

        // return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); -- removed as stops italics in descriptions
        return $text;
    }

    public static function get_xmp($image)
    {
        $content = file_get_contents($image);
        $xmp_data_start = strpos($content, '<x:xmpmeta');
        $xmp_data_end = strpos($content, '</x:xmpmeta>');
        if ($xmp_data_start === FALSE || $xmp_data_end === FALSE) {
            return array();
        }
        $xmp_length = $xmp_data_end - $xmp_data_start;
        $xmp_data = substr($content, $xmp_data_start, $xmp_length + 12);
        unset($content);
        $xmp = simplexml_load_string($xmp_data);
        if ($xmp === FALSE) {
            return array();
        }
        /* $namespaces = $xmp->getDocNamespaces(true);
          $fields = array();
        foreach ($namespaces as $namespace){
          $fields[] = exif_custom_xml_recursion($xmp->children($namespace));
        }*/

        $field_data = array();
        ImageImportSettingsForm::xml_recursion($xmp, $field_data, 'XMP');

        return $field_data;
    }

    public static function xml_recursion($obj, &$fields, $name)
    {
        $namespace = $obj->getDocNamespaces(true);
        $namespace[NULL] = NULL;

        $children = array();
        $attributes = array();
        $name = $name . ':' . strtolower((string)$obj->getName());

        $text = trim((string)$obj);
        if (strlen($text) <= 0) {
            $text = NULL;
        }

        // get info for all namespaces
        if (is_object($obj)) {
            foreach ($namespace as $ns => $nsUrl) {
                // atributes
                $objAttributes = $obj->attributes($ns, true);
                foreach ($objAttributes as $attributeName => $attributeValue) {
                    $attribName = strtolower(trim((string)$attributeName));
                    $attribVal = trim((string)$attributeValue);
                    if (!empty($ns)) {
                        $attribName = $ns . ':' . $attribName;
                    }
                    $attributes[$attribName] = $attribVal;
                }

                // children
                $objChildren = $obj->children($ns, true);
                foreach ($objChildren as $childName => $child) {
                    $childName = strtolower((string)$childName);
                    if (!empty($ns)) {
                        $childName = $ns . ':' . $childName;
                    }
                    $children[$childName][] = ImageImportSettingsForm::xml_recursion($child, $fields, $name);
                }
            }
        }
        if (!is_null($text)) {
            $fields[$name] = $text;
        }

        return array(
            'name' => $name,
            'text' => html_entity_decode($text),
            'attributes' => $attributes,
            'children' => $children
        );
    }

    public static function getHumanReadableKey($text)
    {
        if (!strncmp($text, 'IPTC:', 5)) {
            return 'IPTC:' . ImageImportSettingsForm::getHumanReadableIPTCkey(substr($text, 5));
        } else {
            return $text;
        }
    }

    /**
     * Just some little helper function to get the iptc fields
     * @return array
     *
     */
    public static function getHumanReadableIPTCkey($text)
    {
        $pairs = array(
            "2#202" => "object_data_preview_data",
            "2#201" => "object_data_preview_file_format_version",
            "2#200" => "object_data_preview_file_format",
            "2#154" => "audio_outcue",
            "2#153" => "audio_duration",
            "2#152" => "audio_sampling_resolution",
            "2#151" => "audio_sampling_rate",
            "2#150" => "audio_type",
            "2#135" => "language_identifier",
            "2#131" => "image_orientation",
            "2#130" => "image_type",
            "2#125" => "rasterized_caption",
            "2#122" => "writer",
            "2#120" => "caption",
            "2#118" => "contact",
            "2#116" => "copyright_notice",
            "2#115" => "source",
            "2#110" => "credit",
            "2#105" => "headline",
            "2#103" => "original_transmission_reference",
            "2#101" => "country_name",
            "2#100" => "country_code",
            "2#095" => "state",
            "2#092" => "sublocation",
            "2#090" => "city",
            "2#085" => "by_line_title",
            "2#080" => "by_line",
            "2#075" => "object_cycle",
            "2#070" => "program_version",
            "2#065" => "originating_program",
            "2#063" => "digital_creation_time",
            "2#062" => "digital_creation_date",
            "2#060" => "creation_time",
            "2#055" => "creation_date",
            "2#050" => "reference_number",
            "2#047" => "reference_date",
            "2#045" => "reference_service",
            "2#042" => "action_advised",
            "2#040" => "special_instruction",
            "2#038" => "expiration_time",
            "2#037" => "expiration_date",
            "2#035" => "release_time",
            "2#030" => "release_date",
            "2#027" => "content_location_name",
            "2#026" => "content_location_code",
            "2#025" => "keywords",
            "2#022" => "fixture_identifier",
            "2#020" => "supplemental_category",
            "2#015" => "category",
            "2#010" => "subject_reference",
            "2#010" => "urgency",
            "2#008" => "editorial_update",
            "2#007" => "edit_status",
            "2#005" => "object_name",
            "2#004" => "object_attribute_reference",
            "2#003" => "object_type_reference",
            "2#000" => "record_version",
            "1#090" => "envelope_character_set"
        );
        return $pairs[$text];
    }
}
