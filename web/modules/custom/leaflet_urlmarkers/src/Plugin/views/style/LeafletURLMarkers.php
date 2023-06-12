<?php

namespace Drupal\leaflet_urlmarkers\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\leaflet_views\Plugin\views\style\LeafletMap;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\BubbleableMetadata;


/**
 * Style plugin, extends Leaflet_views
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "Leaflet URL markers",
 *   title = @Translation("Leaflet URL markers"),
 *   help = @Translation("Leaflet URL markers help."),
 *   theme = "views_view_leaflet_urlmarkers",
 *   display_types = { "normal" }
 * )
 */
class LeafletURLMarkers extends LeafletMap {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['clickToUrl'] = ['default' => FALSE];
    return $options;
  }
  
    /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    $form['clickToUrl'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Click on marker opens URL from Description field. No popups'),
      '#default_value' => $this->options['clickToUrl'],
      ];
  }
  
  /**
   * Renders the View.
   */
  public function render() {
    $features_groups = [];
    $element = [];

    // Collect bubbleable metadata when doing early rendering.
    $build_for_bubbleable_metadata = [];

    // Always render the map, otherwise ...
    $leaflet_map_style = !isset($this->options['leaflet_map']) ? $this->options['map'] : $this->options['leaflet_map'];
    $map = leaflet_map_get_info($leaflet_map_style);

    // Set Map additional map Settings.
    $this->setAdditionalMapOptions($map, $this->options);

    // Add a specific map id.
    $map['id'] = Html::getUniqueId("leaflet_map_view_" . $this->view->id() . '_' . $this->view->current_display);
    
    //START NEW CODE
    $viewid = $this->view->id();
    $arguments = $this->view->args;
    $counter = 0;
    //END NEW CODE

    // Define the list of geofields set as source of Leaflet View geodata,
    // with backword compatibility with the previous version (8.1.22) when only
    // one Geofield was possible as geodata source.
    $geofield_names = is_array($this->options['data_source']) ? $this->options['data_source'] : [$this->options['data_source']];

    // If the Geofield field is null, output a warning
    // to the Geofield Map administrator.
    if (empty($geofield_names) && $this->currentUser->hasPermission('configure geofield_map')) {
      $element = [
        '#markup' => '<div class="geofield-map-warning">' . $this->t("The Geofield field has not been correctly set for this View. <br>Add at least one Geofield to the View and set it as Data Source in the Geofield Google Map View Display Settings.") . "</div>",
        '#attached' => [
          'library' => ['leaflet/general'],
        ],
      ];
    }

    if (!empty($geofield_names) && (!empty($this->view->result) || !$this->options['hide_empty_map'])) {
      $this->renderFields($this->view->result);

      // Group the rows according to the grouping instructions, if specified.
      $view_results_groups = $this->renderGrouping(
        $this->view->result,
        $this->options['grouping'],
        TRUE
      );

      foreach ($view_results_groups as $group_label => $view_results_group) {
        $features_group = [];
        // Sanitize the Group Label from Tags and invisible characters.
        $group_label = str_replace(["\n", "\r"], "", strip_tags($group_label));
        


        // Iterate on each geofields set as source of Leaflet View geodata.
        foreach ($geofield_names as $geofield_name) {

          if (isset($this->view->field[$geofield_name])) {

            foreach ($view_results_group['rows'] as $id => $result) {
              //NEW CODE
              //count rows, even if they don't have geofield values
              $counter++;
              //END NEW CODE 
              
              // For proper processing make sure the geofield_value is created
              // as an array, also if single value.
              $geofield_value = $this->view->field[$geofield_name] ? (array) $this->getFieldValue($id, $geofield_name) : [];

              // Allow other modules to add/alter the $geofield_value
              // and the $map.
              $leaflet_view_geofield_value_alter_context = [
                'leaflet_map_style' => $leaflet_map_style,
                'result' => $result,
                'leaflet_view_style' => $this,
              ];
              $this->moduleHandler->alter('leaflet_map_view_geofield_value', $geofield_value, $map, $leaflet_view_geofield_value_alter_context);

              if (!empty($geofield_value)) {
                $features = $this->leafletService->leafletProcessGeofield($geofield_value);

                $entity_id = NULL;
                $entity_type = NULL;
                $entity_language = NULL;
                if (!empty($result->_entity)) {
                  // Entity API provides a plain entity object.
                  $entity = $result->_entity;
                  $entity_id = $entity->id();
                  $entity_type = $entity->getEntityTypeId();
                  $entity_language = $entity->language()->getId();
                }
                elseif (isset($result->_object)) {
                  // Search API provides a TypedData EntityAdapter.
                  $entity_adapter = $result->_object;
                  if ($entity_adapter instanceof EntityAdapter) {
                    $entity = $entity_adapter->getValue();
                    $entity_id = $entity->id();
                    $entity_type = $entity->getEntityTypeId();
                    $entity_language = $entity->language()->getId();
                  }
                }
                elseif ($result instanceof ResultRow) {
                  $id = $result->_item->getId();
                  $search_api_id_parts = explode(':', $result->_item->getId());
                  $id_parts = explode('/', $search_api_id_parts[1]);
                  $entity_id = $id_parts[1] ?? NULL;
                  $entity_type = $id_parts[0] ?? NULL;
                  $entity_language = $search_api_id_parts[2] ?? NULL;
                }

                // Render the entity with the selected view mode.
                if (!empty($entity_id) && !empty($entity_type)) {
                  // Get and set (if not set) the Geofield cardinality.
                  /* @var \Drupal\Core\Field\FieldItemList $geofield_entity */
                  if (!isset($map['geofield_cardinality']) && isset($entity)) {
                    try {
                      $geofield_entity = $entity->get($geofield_name);
                      $map['geofield_cardinality'] = $geofield_entity->getFieldDefinition()
                        ->getFieldStorageDefinition()
                        ->getCardinality();
                    }
                    catch (\Exception $e) {
                      // In case of exception it means that $geofield_name field
                      // is not directly related to the $entity and might be the
                      // case of a geofield exposed through a relationship.
                      // In this case it is complicated to get the geofield
                      // related entity, so apply a more general case of
                      // multiple/infinite geofield_cardinality.
                      // @see: https://www.drupal.org/project/leaflet/issues/3048089
                      $map['geofield_cardinality'] = -1;
                    }
                  }
                  else {
                    $map['geofield_cardinality'] = -1;
                  }

                  $entity_type_langcode_attribute = $entity_type . '_field_data_langcode';

                  $view = $this->view;

                  // Set the langcode to be used for rendering the entity.
                  $rendering_language = $view->display_handler->getOption('rendering_language');
                  $dynamic_renderers = [
                    '***LANGUAGE_entity_translation***' => 'TranslationLanguageRenderer',
                    '***LANGUAGE_entity_default***' => 'DefaultLanguageRenderer',
                  ];
                  if (isset($dynamic_renderers[$rendering_language])) {
                    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
                    $langcode = isset($result->$entity_type_langcode_attribute) ? $result->$entity_type_langcode_attribute : $entity_language;
                  }
                  else {
                    if (strpos($rendering_language, '***LANGUAGE_') !== FALSE) {
                      $langcode = PluginBase::queryLanguageSubstitutions()[$rendering_language];
                    }
                    else {
                      // Specific langcode set.
                      $langcode = $rendering_language;
                    }
                  }

                  // Define the Popup source and Popup view mode with backward
                  // compatibility with Leaflet release < 2.x.
                  $popup_source = !empty($this->options['description_field']) ? $this->options['description_field'] : ($this->options['leaflet_popup']['value'] ?? '');
                  $popup_view_mode = !empty($this->options['view_mode']) ? $this->options['view_mode'] : $this->options['leaflet_popup']['view_mode'];

                  switch ($popup_source) {
                    case '#rendered_entity':
                      $build = $this->entityManager->getViewBuilder($entity_type)
                        ->view($entity, $popup_view_mode, $langcode);
                      $render_context = new RenderContext();
                      $popup_content = $this->renderer->executeInRenderContext($render_context, function () use (&$build) {
                        return $this->renderer->render($build, TRUE);
                      });
                      if (!$render_context->isEmpty()) {
                        $render_context->update($build_for_bubbleable_metadata);
                      }
                      break;

                    case '#rendered_entity_ajax':
                      $parameters = [
                        'entity_type' => $entity_type,
                        'entity' => $entity_id,
                        'view_mode' => $popup_view_mode,
                        'langcode' => $langcode,
                      ];
                      $url = Url::fromRoute('leaflet_views.ajax_popup', $parameters);
                      $popup_content = sprintf('<div class="leaflet-ajax-popup" data-leaflet-ajax-popup="%s" %s></div>',
                        $url->toString(), LeafletAjaxPopupController::getPopupIdentifierAttribute($entity_type, $entity_id, $this->options['leaflet_popup']['view_mode'], $langcode));
                      $map['settings']['ajaxPoup'] = TRUE;
                      break;

                    case '#rendered_view_fields':
                      // Normal rendering via view/row fields
                      // (with labels options, formatters, classes, etc.).
                      $render_row = [
                        "markup" => $this->view->rowPlugin->render($result),
                      ];
                      $popup_content = $this->renderer->renderPlain($render_row);
                      break;

                    default:
                      // Row rendering of single specified field value (without
                      // labels).
                      $popup_content = !empty($popup_source) ? $this->rendered_fields[$result->index][$popup_source] : '';
                  }

                  // Eventually merge map icon definition
                  // from hook_leaflet_map_info.
                  if (!empty($map['icon'])) {
                    $this->options['icon'] = $this->options['icon'] ?: [];

                    // Remove empty icon options so that they might be replaced
                    // by the ones set by the hook_leaflet_map_info.
                    foreach ($this->options['icon'] as $k => $icon_option) {
                      if (empty($icon_option) || (is_array($icon_option) && $this->leafletService->multipleEmpty($icon_option))) {
                        unset($this->options['icon'][$k]);
                      }
                    }
                    $this->options['icon'] = array_replace($map['icon'], $this->options['icon']);
                  }

                  // Define possible tokens.
                  $tokens = [];
                  foreach ($this->rendered_fields[$result->index] as $field_name => $field_value) {
                    $tokens[$field_name] = $field_value;
                  }

                  $icon_type = isset($this->options['icon']['iconType']) ? $this->options['icon']['iconType'] : 'marker';

                  // Relates each result feature with additional properties.
                  foreach ($features as &$feature) {
                    

                    // Attach pop-ups if we have a description field.
                    // Add its entity id, so it might be referenced from
                    // outside.
                    $feature['entity_id'] = $entity_id;

                    // Generate the weight feature property
                    // (falls back to natural result ordering).
                    $feature['weight'] = !empty($this->options['weight']) ? intval(str_replace([
                      "\n",
                      "\r",
                    ], "", $this->viewsTokenReplace($this->options['weight'], $tokens))) : $id;

                    // Attach pop-ups if we have a description field.
                    // START NEW CODE
                    if ($this->options['clickToUrl']) {
                        // Attach a target URL if we want clicking on the marker to open an URL. In this case, we don't use pop-ups
                        if ($viewid == "carte_des_photos") {
                            // Custom logic for photomap 
                            //setting url back to photo gallery - example: http://tobiasb/photos?field_tags_target_id=31#1
                            $feature['targetUrl'] = $GLOBALS['base_url'] . '/photos?field_tags_target_id=' . $arguments[0] .'#' . (string) ($counter);
                        }
                        else {
                           //DEFAULT CASE    
                           $feature['targetUrl'] = $description;
                        }
                    }
                    elseif (!empty($popup_content)) {
                        $feature['popup']['value'] = $popup_content;
                        $feature['popup']['options'] = $this->options['leaflet_popup'] ? $this->options['leaflet_popup']['options'] : NULL;
                    }
                    // END NEW CODE
                    
                    /* START OLD CODE
                    if (!empty($popup_content)) {
                      $feature['popup']['value'] = $popup_content;
                      $feature['popup']['options'] = $this->options['leaflet_popup'] ? $this->options['leaflet_popup']['options'] : NULL;
                    }
                    /* END OLD CODE */
                    
                    // Attach tooltip data (value & options),
                    // if tooltip value is not empty.
                    if (!empty($this->options['leaflet_tooltip']['value'])) {
                      $feature['tooltip'] = $this->options['leaflet_tooltip'];
                      // Decode any entities because JS will encode them again,
                      // and we don't want double encoding.
                      $feature['tooltip']['value'] = !empty($this->options['leaflet_tooltip']['value']) ? Html::decodeEntities(($this->rendered_fields[$result->index][$this->options['leaflet_tooltip']['value']])) : '';
                    }
                    // Otherwise eventually attach simple title tooltip.
                    elseif ($this->options['name_field']) {
                      // Decode any entities because JS will encode them again,
                      // and we don't want double encoding.
                      $feature['title'] = !empty($this->options['name_field']) ? Html::decodeEntities(($this->rendered_fields[$result->index][$this->options['name_field']])) : '';
                    }

                    // Eventually set the custom Marker icon (DivIcon, Icon Url
                    // or Circle Marker).
                    // Custom behaviour for photo map
                    if ($feature['type'] === 'point' && $viewid == "carte_des_photos") {
                        $test = $this->rendered_fields[$id]['nid'];
                        if ($test <> $arguments[1]) {
                            // dark blue frame
                            $feature['icon']['iconUrl'] = $this->rendered_fields[$id]['field_image_1'];
                            } else {
                            
                            // icon that shall be in the center
                            $feature['icon']['iconUrl'] = $this->rendered_fields[$id]['field_image'];
                            $center = array(
                                'lat' => $feature['lat'],
                                'lon' => $feature['lon']);
                            // put this icon in front of all others
                            $feature['zIndexOffset'] = 1000;

                            }
                    }
                    elseif ($feature['type'] === 'point' && isset($this->options['icon'])) {
                    // END NEW CODE
                    // START OLD CODE
                    ////if ($feature['type'] === 'point' && isset($this->options['icon'])) {
                    // END OLD CODE
                      // Set Feature Icon properties.
                      $feature['icon'] = $this->options['icon'];

                      // Transforms Icon Options that support Replacement
                      // Patterns/Tokens.
                      if (!empty($this->options["icon"]["iconSize"]["x"])) {
                        $feature['icon']["iconSize"]["x"] = $this->viewsTokenReplace($this->options["icon"]["iconSize"]["x"], $tokens);
                      }
                      if (!empty($this->options["icon"]["iconSize"]["y"])) {
                        $feature['icon']["iconSize"]["y"] = $this->viewsTokenReplace($this->options["icon"]["iconSize"]["y"], $tokens);
                      }
                      if (!empty($this->options["icon"]["shadowSize"]["x"])) {
                        $feature['icon']["shadowSize"]["x"] = $this->viewsTokenReplace($this->options["icon"]["shadowSize"]["x"], $tokens);
                      }
                      if (!empty($this->options["icon"]["shadowSize"]["y"])) {
                        $feature['icon']["shadowSize"]["y"] = $this->viewsTokenReplace($this->options["icon"]["shadowSize"]["y"], $tokens);
                      }

                      switch ($icon_type) {
                        case 'html':
                          $feature['icon']['html'] = str_replace([
                            "\n",
                            "\r",
                          ], "", $this->viewsTokenReplace($this->options['icon']['html'], $tokens));
                          $feature['icon']['html_class'] = $this->options['icon']['html_class'];
                          break;

                        case 'circle_marker':
                          $feature['icon']['options'] = str_replace([
                            "\n",
                            "\r",
                          ], "", $this->viewsTokenReplace($this->options['icon']['circle_marker_options'], $tokens));
                          break;

                        default:
                          // Apply Token Replacements to iconUrl & shadowUrl.
                          if (!empty($this->options['icon']['iconUrl'])) {
                            $feature['icon']['iconUrl'] = str_replace([
                              "\n",
                              "\r",
                            ], "", $this->viewsTokenReplace($this->options['icon']['iconUrl'], $tokens));
                            // Generate correct Absolute iconUrl & shadowUrl,
                            // if not external.
                            if (!empty($feature['icon']['iconUrl'])) {
                              $feature['icon']['iconUrl'] = $this->leafletService->generateAbsoluteString($feature['icon']['iconUrl']);
                            }
                          }
                          if (!empty($this->options['icon']['shadowUrl'])) {
                            $feature['icon']['shadowUrl'] = str_replace([
                              "\n",
                              "\r",
                            ], "", $this->viewsTokenReplace($this->options['icon']['shadowUrl'], $tokens));
                            if (!empty($feature['icon']['shadowUrl'])) {
                              $feature['icon']['shadowUrl'] = $this->leafletService->generateAbsoluteString($feature['icon']['shadowUrl']);
                            }
                          }

                          // Set Feature IconSize and ShadowSize to the IconUrl
                          // or ShadowUrl Image sizes (if empty or invalid).
                          $this->leafletService->setFeatureIconSizesIfEmptyOrInvalid($feature);

                          break;
                      }
                    }

                    // Associate dynamic path properties (token based) to each
                    // feature, in case of not point.
                    if ($feature['type'] !== 'point') {
                      $feature['path'] = str_replace([
                        "\n",
                        "\r",
                      ], "", $this->viewsTokenReplace($this->options['path'], $tokens));
                    }

                    // Associate dynamic className property (token based) to
                    // icon.
                    $feature['icon']['className'] = !empty($this->options['icon']['className']) ? str_replace([
                      "\n",
                      "\r",
                    ], "", $this->viewsTokenReplace($this->options['icon']['className'], $tokens)) : '';

                    // Add Feature additional Properties (if present).
                    if (!empty($this->options['feature_properties']['values'])) {
                      $feature['properties'] = str_replace([
                        "\n",
                        "\r",
                      ], "", $this->viewsTokenReplace($this->options['feature_properties']['values'], $tokens));
                    }

                    // Add eventually the Marker Cluster Exclude Flag.
                    if ($this->options['leaflet_markercluster'] && $this->options['leaflet_markercluster']['control'] && !empty($this->options['leaflet_markercluster']['excluded'])) {
                      $feature['markercluster_excluded'] = !empty(str_replace([
                        "\n",
                        "\r",
                      ], "", strip_tags($this->rendered_fields[$result->index][$this->options['leaflet_markercluster']['excluded']])));
                    }

                    // Eventually Add the belonging Group Label/Name to each
                    // Feature, for possible based logics.
                    if (count($view_results_groups) > 1) {
                      $feature['group_label'] = $group_label;
                    }

                    // Allow modules to adjust the single feature (marker).
                    $this->moduleHandler->alter('leaflet_views_feature', $feature, $result, $this->view->rowPlugin);
                  }                  
                }

                // Generate a single Features Group as incremental Features.
                $features_group = array_merge($features_group, $features);
              }
            }
            
          }
        }
        // Order the data features based on the 'weight' element.
        uasort($features_group, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);

        // Generate Features Groups in case of Grouping.
        if (count($view_results_groups) > 1) {
          // Generate the Features Group.
          $group = [
            'group' => count($view_results_groups) > 1,
            'group_label' => $group_label,
            'disabled' => FALSE,
            'features' => $features_group,
            'weight' => 1,
          ];

          if (isset($this->options["grouping"][0]) && !empty($this->options["grouping"][0]["overlays_options"]["hidden_overlays_controls"])) {
            $group['group_label'] = !array_key_exists($group_label, $this->options["grouping"][0]["overlays_options"]["hidden_overlays_controls"]) ? $group_label : NULL;
          }

          if (isset($this->options["grouping"][0]) && !empty($this->options["grouping"][0]["overlays_options"]["disabled_overlays"])) {
            $group['disabled'] = array_key_exists($group_label, $this->options["grouping"][0]["overlays_options"]["disabled_overlays"]);
          }

          // Allow modules to adjust the single features group.
          $this->moduleHandler->alter('leaflet_views_features_group', $group, $this);

          // Add the Group to the Features Groups array/list.
          $features_groups[] = $group;
        }
      }

      // Order the data features groups based on the 'weight' element.
      uasort($features_group, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);

      //NEW CODE - CUSTOM SETTINGS FOR PHOTOMAP
      if ($viewid == "carte_des_photos") {
           //center map on the photo we clicked. By default, only zooming out
           $map['settings']['zoom'] = 18;
           $map['settings']['center'] = $center;
           $map['settings']['map_position_force'] = 1;
      }
      //END NEW CODE
      
      // Define the Js Settings.
      // Features is defined as Features Groups or single Features in case of a
      // single Features Group (no Grouping active)
      $js_settings = [
        'map' => $map,
        'features' => count($view_results_groups) > 1 ? $features_groups : ($features_group ?? []),
      ];

      // Allow other modules to add/alter the map js settings.
      $this->moduleHandler->alter('leaflet_map_view_style', $js_settings, $this);
      
      $map_height = !empty($this->options['height']) ? $this->options['height'] . $this->options['height_unit'] : '';
      $element = $this->leafletService->leafletRenderMap($js_settings['map'], $js_settings['features'], $map_height);

      // Add the Core Drupal Ajax library for Ajax Popups.
      if (isset($map['settings']['ajaxPoup']) && $map['settings']['ajaxPoup']) {
        $build_for_bubbleable_metadata['#attached']['library'][] = 'core/drupal.ajax';
      }
      BubbleableMetadata::createFromRenderArray($element)
        ->merge(BubbleableMetadata::createFromRenderArray($build_for_bubbleable_metadata))
        ->applyTo($element);
       
    }
    return $element;
  }
  
}
