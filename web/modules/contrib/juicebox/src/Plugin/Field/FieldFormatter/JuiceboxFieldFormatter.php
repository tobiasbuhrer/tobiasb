<?php

namespace Drupal\juicebox\Plugin\Field\FieldFormatter;

use Drupal\Core\Utility\Error;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\media\Entity\MediaType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\juicebox\JuiceboxFormatterInterface;
use Drupal\juicebox\JuiceboxGalleryInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityDisplayRepository;

/**
 * Plugin implementation of the 'juicebox' formatter.
 *
 * @FieldFormatter(
 *   id = "juicebox_formatter",
 *   label = @Translation("Juicebox Gallery"),
 *   field_types = {
 *     "image",
 *     "file",
 *     "entity_reference"
 *   },
 * )
 */
class JuiceboxFieldFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * A Juicebox formatter service.
   *
   * @var \Drupal\juicebox\JuiceboxFormatterInterface
   */
  protected $juicebox;

  /**
   * A Drupal entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A Drupal link generator service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $linkGenerator;

  /**
   * A Symfony request object for the current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity_display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepo;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LinkGeneratorInterface $link_generator, RequestStack $request_stack, JuiceboxFormatterInterface $juicebox, RendererInterface $renderer, EntityDisplayRepository $entity_display_repo) {
    parent::__construct($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['label'], $configuration['view_mode'], $configuration['third_party_settings']);
    $this->entityTypeManager = $entity_type_manager;
    $this->linkGenerator = $link_generator;
    $this->request = $request_stack->getCurrentRequest();
    $this->juicebox = $juicebox;
    $this->renderer = $renderer;
    $this->entityDisplayRepo = $entity_display_repo;
  }

  /**
   * Factory to fetch required dependencies from container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // Create a new instance of the plugin. This also allows us to extract
    // services from the container and inject them into our plugin via its own
    // constructor as needed.
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('link_generator'), $container->get('request_stack'), $container->get('juicebox.formatter'), $container->get('renderer'), $container->get('entity_display.repository'));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    // This is a static method so we can't use the injected JuiceboxFormatter
    // service. Instead we must get our own from the container.
    $juicebox = \Drupal::service('juicebox.formatter');
    $library = $juicebox->getLibrary();
    return [
      // If the library supports multi-size we can default to that for the
      // main image, otherwise use the "medium" style.
      'image_style' => (!empty($library['version']) && !in_array('juicebox_multisize_image_style', $library['disallowed_conf'])) ? 'juicebox_multisize' : 'juicebox_medium',
      'thumb_style' => 'juicebox_square_thumb',
      'caption_source' => '',
      'title_source' => '',
    ] + $juicebox->confBaseOptions() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    // Detect if this is a "pseudo" instance such that the field's context is
    // managed by something other than the core Field API (e.g., a fake instance
    // used for a a view row). This case is supported but we still want to put
    // up a notice about it.
    if ($this->isPseudoInstance()) {
      $element['instance_warning'] = [
        '#prefix' => '<div class="messages messages--warning">',
        '#markup' => $this->t('<strong>WARNING:</strong> You appear to be using the Juicebox field formatter with a field instance that is not directly attached to an entity. Support for this configuration is currently experimental. Please test your final gallery output thoroughly.'),
        '#suffix' => '</div>',
      ];
    }
    // Get available title and caption sources.
    $text_sources = $this->getFieldTextSources();
    // Add the field-formatter-specific elements.
    $element['image_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Main Image Style'),
      '#default_value' => $this->getSetting('image_style'),
      '#description' => $this->t('The style formatter for the main image.'),
      '#options' => $this->juicebox->confBaseStylePresets(),
      '#empty_option' => $this->t('None (original image)'),
    ];
    $element['thumb_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Thumbnail Style'),
      '#default_value' => $this->getSetting('thumb_style'),
      '#description' => $this->t('The style formatter for the thumbnail.'),
      '#options' => $this->juicebox->confBaseStylePresets(FALSE),
      '#empty_option' => $this->t('None (original image)'),
    ];
    $element['caption_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Caption Source'),
      '#default_value' => $this->getSetting('caption_source'),
      '#description' => $this->t('The image value that should be used for the caption.'),
      '#options' => $text_sources,
      '#empty_option' => $this->t('No caption'),
    ];
    $element['title_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Title Source'),
      '#default_value' => $this->getSetting('title_source'),
      '#description' => $this->t('The image value that should be used for the title.'),
      '#options' => $text_sources,
      '#empty_option' => $this->t('No title'),
    ];
    // Add the common configuration options.
    $element = $this->juicebox->confBaseForm($element, $this->getSettings());
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();
    // Get available image style presets.
    $presets = $this->juicebox->confBaseStylePresets();
    $settings_display = [];
    // Image style setting.
    if (!empty($settings['image_style']) && isset($presets[$settings['image_style']])) {
      $style = $presets[$settings['image_style']];
    }
    else {
      $style = $this->t('Original Image');
    }
    $settings_display[] = $this->t("Image style: @style", ['@style' => $style]);
    // Thumb style setting.
    if (!empty($settings['thumb_style']) && isset($presets[$settings['thumb_style']])) {
      $style = $presets[$settings['thumb_style']];
    }
    else {
      $style = $this->t('Original Image');
    }
    $settings_display[] = $this->t("Thumbnail style: @style", ['@style' => $style]);
    // Define display options for caption and title source.
    $text_sources = $this->getFieldTextSources();
    // Caption source setting.
    if (!empty($text_sources[$settings['caption_source']])) {
      $source = $text_sources[$settings['caption_source']];
    }
    else {
      $source = $this->t('None');
    }
    $settings_display[] = $this->t("Caption source: @source", ['@source' => $source]);
    // Title source setting.
    if (!empty($text_sources[$settings['title_source']])) {
      $source = $text_sources[$settings['title_source']];
    }
    else {
      $source = $this->t('None');
    }
    $settings_display[] = $this->t("Title source: @source", ['@source' => $source]);
    // Add-in a note about the additional fieldsets.
    $settings_display[] = $this->t("Additional Juicebox library configuration options may also be set.");
    return $settings_display;
  }

  /**
   * {@inheritdoc}
   *
   * We must further check that any media references only target bundles that
   * have a source plugin ID of "image".
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $applicable = parent::isApplicable($field_definition);
    if ($applicable && $field_definition->getSetting('target_type') === 'media') {
      $handler_settings = $field_definition->getSetting('handler_settings');

      // If no target bundles are set, assume all are allowed.
      $allowed_bundles = $handler_settings['target_bundles'] ??
        array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('media'));

      // Filter out all valid bundles.
      $invalid_bundles = array_filter($allowed_bundles, static function ($allowed_bundle) {
        return MediaType::load($allowed_bundle)->getSource()->getPluginId() !== 'image';
      });

      // Only apply to this field if there are no invalid bundles!
      $applicable = empty($invalid_bundles);
    }

    return $applicable;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    // If there are no images, don't do anything else.
    if ($items->isEmpty()) {
      return [];
    }
    $entity = $items->getEntity();
    $field_instance = $items->getFieldDefinition();
    $entity_type_id = $entity->getEntityTypeId();
    $entity_id = $entity->id();
    $field_name = $field_instance->getName();
    $display_name = $this->viewMode;
    $add_js = TRUE;
    // Check for incompatible view modes - see issue #2217791.
    if ($display_name == 'search_result' || $display_name == 'search_index') {
      $add_js = FALSE;
    }
    if ($entity->isNew()) {
      // If an entity has yet to be saved, it will not have an ID. Furthermore,
      // the entity is unlikely to have any data that could form an intuitive
      // preview.  Because it's likely that there isn't enough data, we'll use
      // a "dummy" image to provide a best-effort representation of what might
      // be seen by end-users.
      $gallery = $this->juicebox->newGallery(['id' => Html::getUniqueId('juicebox-preview')]);
      $src = 'data:image/png;base64,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';
      $gallery->addImage([
        'juicebox_compatible' => true,
        'unstyled_src' => $src,
        'imageURL' => $src,
        'thumbURL' => $src,
        'linkURL' => $src,
        'linkTarget' => '_blank',
      ]);
      $xml_route_info = [
        'route_name' => 'juicebox.xml_field',
        'route_parameters' => [
          'entityType' => $entity_type_id,
          'entityId' => 0,
          'fieldName' => $field_name,
          'displayName' => $display_name,
        ],
        'options' => ['query' => $this->request->query->all()],
      ];
      return $this->juicebox->buildEmbed($gallery, $this->getSettings(), $xml_route_info, TRUE, TRUE, []);
    }
    elseif (!empty($entity->in_preview)) {
      // The gallery shown in preview view will only display field data from the
      // previously saved version (that is the only version the XML generation
      // methods will have access to). Display a warning because of this.
      $this->messenger()->addWarning($this->t('Juicebox galleries may not display correctly in preview mode. Any edits made to gallery data will only be visible after all changes are saved.'), FALSE);
    }
    // Generate xml details.
    $xml_route_info = [
      'route_name' => 'juicebox.xml_field',
      'route_parameters' => [
        'entityType' => $entity_type_id,
        'entityId' => $entity_id,
        'fieldName' => $field_name,
        'displayName' => $display_name,
      ],
      'options' => ['query' => $this->request->query->all()],
    ];
    // Try building the gallery and its XML.
    try {
      // Initialize the gallery.
      $gallery = $this->juicebox->newGallery($xml_route_info['route_parameters']);
      // Build the gallery.
      $this->buildGallery($gallery, $items);
      // Build field-specific contextual links.
      $contextual = $this->buildContextualLinks($xml_route_info, $entity_type_id);
      // Create a render array with the gallery markup.
      $element[0] = $this->juicebox->buildEmbed($gallery, $this->getSettings(), $xml_route_info, $add_js, $this->isPseudoInstance(), $contextual);
    }
    catch (\Exception $e) {
      $message = 'Exception building Juicebox embed code for field: !message in %function (line %line of %file).';
      Error::logException(\Drupal::logger('juicebox'), $e, $message);
    }
    return $element;
  }

  /**
   * Utility to build a Juicebox gallery based on field formatter data.
   *
   * @param Drupal\juicebox\JuiceboxGalleryInterface $gallery
   *   An initialized Juicebox gallery object.
   * @param Drupal\Core\Field\FieldItemListInterface $items
   *   A list of field items that contain file data for the gallery.
   */
  protected function buildGallery(JuiceboxGalleryInterface $gallery, FieldItemListInterface $items) {
    // Get settings.
    $settings = $this->getSettings();
    // Iterate over items and extract image data.
    foreach ($items as $item) {
      if ($item->getPluginId() === 'field_item:entity_reference') {
        $item = $item->entity->field_media_image[0];
      }
      if ($item->isDisplayed() && !empty($item->target_id)) {
        // Calculate the source data that Juicebox requires.
        $src_data = $this->juicebox->styleImageSrcData($item->entity, $settings['image_style'], $item->entity, $settings['thumb_style'], $settings);
        // Short-circut this iteration if skipping an incompatible file.
        if (!$src_data['juicebox_compatible'] && $settings['incompatible_file_action'] == 'skip') {
          continue;
        }
        // Set the image title. If we have an incompatible file and are
        // configured to show a link, set the title text as the link.
        if (!$src_data['juicebox_compatible'] && $settings['incompatible_file_action'] == 'show_icon_and_link') {
          $anchor = !empty($item->description) ? $item->description : $item->entity->get('filename')->value;
          $title = $this->linkGenerator->generate($anchor, Url::fromUri($src_data['linkURL']));
        }
        else {
          $title = $this->getFieldText($item, $settings['title_source']);
        }
        // Set the image caption.
        $caption = $this->getFieldText($item, $settings['caption_source']);
        // Add this image to the gallery.
        $gallery->addImage($src_data, $title, $caption);
      }
    }
    // Run common build tasks. This is also where the general settings are
    // applied.
    $this->juicebox->runCommonBuild($gallery, $settings, $items);
  }

  /**
   * Utility to build contextual links for a field-based gallery display.
   *
   * @param array $xml_route_info
   *   Associative array of route info used to generate the XML.
   * @param string $entity_type_id
   *   The entity type for this field instance.
   *
   * @return array
   *   An associated array of calculated contextual link information.
   */
  protected function buildContextualLinks(array $xml_route_info, $entity_type_id) {
    $contextual = [];
    // These links won't be reliable unless we have a true field instance.
    if (!$this->isPseudoInstance()) {
      // Add a contextual link to view the XML. Note that we include any query
      // params as route paramaters. These won't be used in the actual route
      // but they will be preserved as query paramaters on the contextual link
      // (which may be needed during the XML request).
      $xml_query = !empty($xml_route_info['options']['query']) ? $xml_route_info['options']['query'] : [];
      $contextual['juicebox_xml_field'] = [
        'route_parameters' => $xml_route_info['route_parameters'] + $xml_query,
      ];
      // Calculate a contextual link that can be used to edit the gallery type.
      // @see \Drupal\juicebox\Plugin\Derivative\JuiceboxConfFieldContextualLinks::getDerivativeDefinitions()
      $bundle = $this->fieldDefinition->getTargetBundle();
      $display_entity = $this->entityDisplayRepo->getViewDisplay($entity_type_id, $bundle, $this->viewMode);
      $contextual['juicebox_conf_field_' . $entity_type_id] = [
        'route_parameters' => [
          'view_mode_name' => (!$display_entity->status() || $display_entity->isNew()) ? 'default' : $this->viewMode,
        ],
      ];
      // Some entity types require that a bundle be added to the route params.
      $entity_types = $this->entityTypeManager->getDefinitions();
      $bundle_entity_type = $entity_types[$entity_type_id]->getBundleEntityType();
      if (!empty($bundle_entity_type)) {
        $contextual['juicebox_conf_field_' . $entity_type_id]['route_parameters'][$bundle_entity_type] = $bundle;
      }
    }
    return $contextual;
  }

  /**
   * Utility to fetch the title and caption source options.
   *
   * Source options for field-based galleries based on the properties
   * available for this field.
   *
   * @return array
   *   An associative array representing the key => label pairs for each title
   *   and caption source option.
   */
  protected function getFieldTextSources() {
    // The filename should always be an available option.
    $text_source_options['filename'] = $this->t('File - Filename (processed by fallback text format)');
    $field_settings = $this->getFieldSettings();
    // Check if this is a "pseudo" instance such that the field is managed by
    // something other than the core Field API (e.g., a fake instance used
    // view row). In this case the instance data is most likely fake, and cannot
    // tell us anything about what field options are available. When this
    // happens we pretend all relevent instance options are active.
    if ($this->isPseudoInstance() || $this->isEntityReference()) {
      foreach (['alt_field', 'title_field', 'description_field'] as $value) {
        $field_settings[$value] = TRUE;
      }
    }
    if (!empty($field_settings)) {
      // If this is a standard image field we can use core image "alt" and
      // "title" values if they are active.
      if (!empty($field_settings['alt_field'])) {
        $text_source_options['alt'] = $this->t('Image - Alt text (processed by fallback text format)');
      }
      if (!empty($field_settings['title_field'])) {
        $text_source_options['title'] = $this->t('Image - Title text (processed by fallback text format)');
      }
      // If this is a standard file field, we can use the core file
      // "description" value if it is active.
      if (!empty($field_settings['description_field'])) {
        $text_source_options['description'] = $this->t('File - Description text (processed by fallback text format)');
      }
    }

    return $text_source_options;
  }

  /**
   * Utility to get sanitized text directly from a field item.
   *
   * This method will attempt to extract text, in a format safe for display,
   * from the data contained within a file item. We have to generate a raw
   * string of text here, as opposed to a render array, beacuse this output must
   * be valid for use in both HTML and XML.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   A field item implementing FieldItemInterface.
   * @param string $source
   *   The source property that contains the text we want to extract. This
   *   property may be part of the item metadata or a property on a referenced
   *   entity.
   *
   * @return string
   *   Safe text for output or an empty string if no text can be extracted.
   */
  protected function getFieldText(FieldItemInterface $item, $source) {
    // If the text source is the filename we need to get the data from the
    // item's related file entity.
    if ($source == 'filename' && isset($item->entity)) {
      $entity_properties = $item->entity->toArray();
      if (isset($entity_properties[$source])) {
        // A processed_text render array will utilize text filters on rendering.
        $text_to_build = [
          '#type' => 'processed_text',
          '#text' => $item->entity->get($source)->value,
        ];
        return $this->renderer->render($text_to_build);
      }
    }
    // Otherwise we are dealing with an item value (such as image alt or title
    // text). For some reason alt and title values are not always set as
    // properties on items, so we can't use $item->get(). However, calling the
    // variable directly triggers __get(), which works for BOTH properties and
    // plain values.
    if (isset($item->{$source}) && is_string($item->{$source})) {
      // A processed_text render array will utilize text filters on rendering.
      $text_to_build = [
        '#type' => 'processed_text',
        '#text' => $item->{$source},
      ];
      return $this->renderer->render($text_to_build);
    }

    return '';
  }

  /**
   * Helper to see if the field our formatter is active on is a pseudo instance.
   *
   * (e.g. not part of a normal entity field instance).
   *
   * @return bool
   *   Returns TRUE is a pseudo instance is detected, false otherwise.
   */
  protected function isPseudoInstance() {
    if ((isset($this->fieldDefinition) && $this->fieldDefinition instanceof FieldConfigInterface) &&
        (isset($this->viewMode) && $this->viewMode != '_custom')) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Helper to check if the field is media and if the target bundle is image.
   *
   * @return boolean
   *   Returns TRUE if the fields is Entity reference and of type image.
   */
  protected function isEntityReference() {
    if ($this->fieldDefinition->getType() != 'entity_reference' &&
      isset($this->fieldDefinition->getSettings()['handler_settings']['target_bundles'])) {
        if (array_keys($this->fieldDefinition->getSettings()['handler_settings']['target_bundles'])[0] == 'image') {
          return true;
        }
    }
  }

}
