<?php

namespace Drupal\imgmeta\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal;
use Drupal\field\Entity\FieldConfig;

/**
 * Plugin implementation of the 'imgmeta_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "imgmeta_field_widget",
 *   label = @Translation("Import data from image EXIF/IPTC fields"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class ImgmetaFieldWidget extends WidgetBase
{

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings()
    {
        return [
                'image_field' => NULL,
                'exif_field' => NULL,
            ] + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state)
    {
        if ($form['#entity_type'] == "node" || $form['#entity_type'] == "media") {
            $entityFieldDefinitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($form["#entity_type"], $form["#bundle"]);
            $options = array();

            foreach ($entityFieldDefinitions as $id => $definition) {
                if ($definition->getType() == 'image') {
                    $options[$id] = $definition->getLabel();
                }
            }
            $element['image_field'] = array(
                '#type' => 'select',
                '#title' => $this->t('Source Image Field'),
                '#default_value' => $this->getSetting('image_field'),
                '#required' => TRUE,
                '#options' => $options,
                '#element_validate' => array(
                    array(
                        get_class($this),
                        'validateImageField'
                    )
                )
            );
        }

        $filepath = drupal_get_path('module', 'imgmeta') .'/sample.jpg';
        $fields = $this->readMetaTags($filepath);

        foreach ($fields as $key => $field) {
            $options[$key] = $this->getHumanReadableKey($key);
        }

        $element['exif_field'] = array(
            '#type' => 'select',
            '#title' => $this->t('EXIF/IPTC/XMP field'),
            '#default_value' => $this->getSetting('exif_field'),
            '#required' => TRUE,
            '#options' => $options,
            '#description' => t('The EXIF, IPTC or XMP field you want to import. The list of tags is taken from the image "sample.jpg". It may not contain all tags available. If you are lookingfor some specific tags you can just replace this image with your own image.'),
        );

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public function settingsSummary()
    {
        $summary = [];
        $image_field = $this->getSetting('image_field');
        if (isset($image_field)) {
            $bundle_name = $this->fieldDefinition->getTargetBundle();
            $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
            $image_field_config = Drupal::getContainer()
                ->get('entity_field.manager')
                ->getFieldDefinitions($entity_type, $bundle_name)[$image_field];
            if ($image_field_config instanceof FieldConfig) {
                if ($image_field_config->getType() == "image" || $image_field_config->getType() == "media") {
                    $label = $this->t("'@image_linked_label' (id: @image_linked_id)",array('@image_linked_label' => $image_field_config->getLabel(), '@image_linked_id' => $image_field));
                } else {
                    $label = $image_field;
                }
            }
            $image_field_msg = $this->t("exif will be extracted from image field @image", array('@image' => $label));
        }
        else {
            $image_field_msg = $this->t('No image chosen. field will stay empty.');
        }
        array_unshift($summary, $image_field_msg);

        $summary[] = $this->t('EXIF/IPTC/XMP field: @exif', array('@exif' => $this->getHumanReadableKey($this->getSetting('exif_field'))));

        return $summary;
    }

    /**
     * Validate.
     */
    public function validate($element, FormStateInterface $form_state) {


        $value = $element['#value'];
        $form_state->setValueForElement($element, 'Value in validate');
        return;
        /*
        if (strlen($value) == 0) {
          $form_state->setValueForElement($element, '');
          return;
        }
        if (!preg_match('/^#([a-f0-9]{6})$/iD', strtolower($value))) {
          $form_state->setError($element, t("Color must be a 6-digit hexadecimal value, suitable for CSS."));
        }*/
    }

    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {
        //get the type of the field
        /*
         * Check out namespace Drupal\Core\Render\Element for all the core provided elements.
         * There are new HTML 5 elements like '#type' => 'tel', '#type' => 'email', '#type' => 'number',
         * '#type' => 'date', '#type' => 'url', '#type' => 'search', '#type' => 'range', etc.
         * Using these elements as opposed to requesting data in plain textfields is preferable because devices
         * can pull up the proper input methods for them, such as when a telephone number is requested, the dialpad would show up on a device.
         * There is also a 'details' type to group several fields, it seems (see Text3Widget.php in colour picker field
         */

        $fieldtype = $this->fieldDefinition->getType();

        switch ($fieldtype) {
            case "datetime":
                $type = 'date';
                break;
            case "string_long":
                $type = 'textarea';
                break;
            default:
                $type = 'textfield';
        }
        //we should get the image
        $image_field = $this->getSetting('source_field');
        $bundle = $this->fieldDefinition->get("bundle");
        //$entity_type = $this->fieldDefinition->get("entity_type");

        $resultmsg = 'we should calculate: ' .  $this->getSetting('exif_field');

        $element += array(
            '#type' => $type,
            '#disabled' => FALSE,
            '#default_value' => $items[$delta]->value ?: $resultmsg,
            '#element_validate' => array(
                array($this, 'validate'),
            )
        );
        return array('value' => $element);
        return $element;
    }

    public static function validateImageField($element, FormStateInterface $form_state, $form) {
        $elementSettings = $form_state->getValue($element['#parents']);
        if (!$elementSettings) {
            $field_storage_definitions = Drupal::getContainer()
                ->get('entity_field.manager')
                ->getFieldStorageDefinitions($form['#entity_type']);
            $field_storage = $field_storage_definitions[$element['#field_name']];
            if ($field_storage) {
                $args = array('%field' => $field_storage->getName());
                $message = $this->t('Field %field must be link to an image field.', $args);
            } else {
                $message = $this->t('Field must be link to an image field.');
            }
            $form_state->setErrorByName('image_field', $message);
        }
    }


    /**
     * calculate default value for settings form (more precisely image_field setting) of widget.
     * Look for the first image field found.
     * @param $widget
     * @param $image_fields
     */
    private function retrieveImageFieldDefaultValue($widget, $image_fields)
    {
        if (array_key_exists('settings', $widget) && array_key_exists('image_field', $widget['settings'])) {
            $result = $widget['settings']['image_field'];
        } else {
            $result = NULL;
        }
        if (empty($result)) {
            //Look for the first image field found.
            $temp = array_keys($image_fields);
            if (!empty($temp) && is_array($temp)) {
                $result = $temp[0];
            }
        }
        return $result;
    }

    private function readMetaTags($uri, $concatenate_arrays = TRUE) {
        $fields = array();

        //Get all of the EXIF tags
        $exif = exif_read_data($uri, NULL, TRUE);
        if(is_array($exif)){
            foreach($exif as $name => $section){
                foreach($section as $key => $value){
                    if($concatenate_arrays && is_array($value)){
                        $value = implode(', ', $value);
                    }
                    $fields['EXIF:' . $name . ':' . $key] = $this->check_plain($value);
                }
            }
        }

        //XMP - test
        $fields =  array_merge($fields, $this->get_xmp($uri));

        //Look for IPTC data
        $size = getimagesize($uri, $info);

        if(is_array($info)){
            foreach ($info as $block){
                $iptc = iptcparse($block);

                if($iptc){
                    //IPTC:2#254 can contain name=value pairs
                    if(isset($iptc['2#254']) && is_array($iptc['2#254'])){
                        $i = 0;
                        foreach($iptc['2#254'] as $iptc_field){
                            $subfields = explode('=', $iptc_field);
                            $iptc['2#254.' . $subfields[0]] = $subfields[1];
                        }
                        unset($iptc['2#254']);
                    }
                    foreach($iptc as $key => $value){
                        if($concatenate_arrays && is_array($value)){
                            $value = implode(', ', $value);
                        }
                        $fields['IPTC:' . $key] = $this->check_plain($value);
                    }
                }
            }
        }

        //TODO: add special treatment for geofield gps import

        if(!is_array($exif) && !isset($iptc)){return FALSE;}
        return $fields;
    }

    private function check_plain($text){
        if (is_null($text)) {
            $text = "";
        }
        if(!mb_detect_encoding($text, 'UTF-8', true)){
            $text = str_replace("&lt;br /&gt;","\n",
                str_replace("<","&lt;",
                    str_replace(">","&gt;",
                        mb_convert_encoding(html_entity_decode($text),"UTF-8","ISO-8859-1"))));
        }

        // return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); -- removed as stops italics in descriptions
        return $text;
    }

    private function get_xmp($image) {
        $content = file_get_contents($image);
        $xmp_data_start = strpos($content, '<x:xmpmeta');
        $xmp_data_end   = strpos($content, '</x:xmpmeta>');
        if ($xmp_data_start === FALSE || $xmp_data_end === FALSE) {
            return array();
        }
        $xmp_length     = $xmp_data_end - $xmp_data_start;
        $xmp_data       = substr($content, $xmp_data_start, $xmp_length + 12);
        unset($content);
        $xmp            = simplexml_load_string($xmp_data);
        if ($xmp === FALSE) {
            return array();
        }
        /* $namespaces = $xmp->getDocNamespaces(true);
          $fields = array();
        foreach ($namespaces as $namespace){
          $fields[] = exif_custom_xml_recursion($xmp->children($namespace));
        }*/

        $field_data = array();
        $this->xml_recursion($xmp, $field_data, 'XMP');

        return $field_data;
    }

    private function xml_recursion($obj, &$fields, $name){
        $namespace = $obj->getDocNamespaces(true);
        $namespace[NULL] = NULL;

        $children = array();
        $attributes = array();
        $name = $name.':'.strtolower((string)$obj->getName());

        $text = trim((string)$obj);
        if( strlen($text) <= 0 ) {
            $text = NULL;
        }

        // get info for all namespaces
        if(is_object($obj)) {
            foreach( $namespace as $ns=>$nsUrl ) {
                // atributes
                $objAttributes = $obj->attributes($ns, true);
                foreach( $objAttributes as $attributeName => $attributeValue ) {
                    $attribName = strtolower(trim((string)$attributeName));
                    $attribVal = trim((string)$attributeValue);
                    if (!empty($ns)) {
                        $attribName = $ns . ':' . $attribName;
                    }
                    $attributes[$attribName] = $attribVal;
                }

                // children
                $objChildren = $obj->children($ns, true);
                foreach( $objChildren as $childName=>$child ) {
                    $childName = strtolower((string)$childName);
                    if( !empty($ns) ) {
                        $childName = $ns.':'.$childName;
                    }
                    $children[$childName][] = $this->xml_recursion($child, $fields, $name);
                }
            }
        }
        if (!is_null($text)){
            $fields[$name] = $text;
        }

        return array(
            'name'=>$name,
            'text'=> html_entity_decode($text),
            'attributes'=>$attributes,
            'children'=>$children
        );
    }

    private function getHumanReadableKey($text) {
        if (!strncmp($text, 'IPTC:', 5)) {
            return 'IPTC:'.$this->getHumanReadableIPTCkey(substr($text,5));
        }
        else {
            return $text;
        }
    }

    /**
     * Just some little helper function to get the iptc fields
     * @return array
     *
     */
    private function getHumanReadableIPTCkey($text) {
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
