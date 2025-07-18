<?php

namespace Drupal\field\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field\EntityDisplayRebuilder;
use Drupal\field\FieldConfigInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\ConfigImporterFieldPurger;
use Drupal\Core\Config\ConfigImporter;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\DynamicallyFieldableEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for field.
 */
class FieldHooks {

  use StringTranslationTrait;

  /**
   * @defgroup field Field API
   * @{
   * Attaches custom data fields to Drupal entities.
   *
   * The Field API allows custom data fields to be attached to Drupal entities
   * and takes care of storing, loading, editing, and rendering field data. Any
   * entity type (node, user, etc.) can use the Field API to make itself
   * "fieldable" and thus allow fields to be attached to it. Other modules can
   * provide a user interface for managing custom fields via a web browser as
   * well as a wide and flexible variety of data type, form element, and display
   * format capabilities.
   *
   * The Field API defines two primary data structures, FieldStorage and Field,
   * and the concept of a Bundle. A FieldStorage defines a particular type of
   * data that can be attached to entities. A Field is attached to a single
   * Bundle. A Bundle is a set of fields that are treated as a group by the
   * Field Attach API and is related to a single fieldable entity type.
   *
   * For example, suppose a site administrator wants Article nodes to have a
   * subtitle and photo. Using the Field API or Field UI module, the
   * administrator creates a field named 'subtitle' of type 'text' and a field
   * named 'photo' of type 'image'. The administrator (again, via a UI) creates
   * two Field Instances, one attaching the field 'subtitle' to the 'node'
   * bundle 'article' and one attaching the field 'photo' to the 'node' bundle
   * 'article'. When the node storage loads an Article node, it loads the values
   * of the 'subtitle' and 'photo' fields because they are both attached to the
   * 'node' bundle 'article'.
   *
   * - @link field_types Field Types API @endlink: Defines field types, widget
   *   types, and display formatters. Field modules use this API to provide
   *   field types like Text and Node Reference along with the associated form
   *   elements and display formatters.
   *
   * - @link field_purge Field API bulk data deletion @endlink: Cleans up after
   *   bulk deletion operations such as deletion of field storage or field.
   */

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.field':
        $field_ui_url = \Drupal::moduleHandler()->moduleExists('field_ui') ? Url::fromRoute('help.page', ['name' => 'field_ui'])->toString() : '#';
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Field module allows custom data fields to be defined for <em>entity</em> types (see below). The Field module takes care of storing, loading, editing, and rendering field data. Most users will not interact with the Field module directly, but will instead use the <a href=":field-ui-help">Field UI module</a> user interface. Module developers can use the Field API to make new entity types "fieldable" and thus allow fields to be attached to them. For more information, see the <a href=":field">online documentation for the Field module</a>.', [
          ':field-ui-help' => $field_ui_url,
          ':field' => 'https://www.drupal.org/documentation/modules/field',
        ]) . '</p>';
        $output .= '<h2>' . $this->t('Terminology') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Entities and entity types') . '</dt>';
        $output .= '<dd>' . $this->t("The website's content and configuration is managed using <em>entities</em>, which are grouped into <em>entity types</em>. <em>Content entity types</em> are the entity types for site content (such as the main site content, comments, content blocks, taxonomy terms, and user accounts). <em>Configuration entity types</em> are used to store configuration information for your site, such as individual views in the Views module, and settings for your main site content types.") . '</dd>';
        $output .= '<dt>' . $this->t('Entity sub-types') . '</dt>';
        $output .= '<dd>' . $this->t('Some content entity types are further grouped into sub-types (for example, you could have article and page content types within the main site content entity type, and tag and category vocabularies within the taxonomy term entity type); other entity types, such as user accounts, do not have sub-types. Programmers use the term <em>bundle</em> for entity sub-types.') . '</dd>';
        $output .= '<dt>' . $this->t('Fields and field types') . '</dt>';
        $output .= '<dd>' . $this->t('Content entity types and sub-types store most of their text, file, and other information in <em>fields</em>. Fields are grouped by <em>field type</em>; field types define what type of data can be stored in that field, such as text, images, or taxonomy term references.') . '</dd>';
        $output .= '<dt>' . $this->t('Formatters and view modes') . '</dd>';
        $output .= '<dd>' . $this->t('Content entity types and sub-types can have one or more <em>view modes</em>, used for displaying the entity items. For instance, a content item could be viewed in full content mode on its own page, teaser mode in a list, or RSS mode in a feed. In each view mode, each field can be hidden or displayed, and if it is displayed, you can choose and configure the <em>formatter</em> that is used to display the field. For instance, a long text field can be displayed trimmed or full-length, and taxonomy term reference fields can be displayed in plain text or linked to the taxonomy term page.') . '</dd>';
        $output .= '<dt>' . $this->t('Widgets and form modes') . '</dd>';
        $output .= '<dd>' . $this->t('Content entity types and sub-types can have one or more <em>form modes</em>, used for editing. For instance, a content item could be edited in a compact format with only some fields editable, or a full format that allows all fields to be edited. In each form mode, each field can be hidden or displayed, and if it is displayed, you can choose and configure the <em>widget</em> that is used to edit the field. For instance, a taxonomy term reference field can be edited using a select list, radio buttons, or an autocomplete widget.') . '</dd>';
        $output .= '</dl>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Enabling field types, widgets, and formatters') . '</dt>';
        $output .= '<dd>' . $this->t('The Field module provides the infrastructure for fields; the field types, formatters, and widgets are provided by Drupal core or additional modules. Some of the modules are required; the optional modules can be installed from the <a href=":modules">Extend administration page</a>. Additional fields, formatters, and widgets may be provided by contributed modules, which you can find in the <a href=":contrib">contributed module section of Drupal.org</a>.', [
          ':modules' => Url::fromRoute('system.modules_list')->toString(),
          ':contrib' => 'https://www.drupal.org/project/modules',
        ]) . '</dd>';
        $output .= '<h2>' . $this->t('Field, widget, and formatter information') . '</h2>';
        // Make a list of all widget, formatter, and field modules currently
        // enabled, ordered by displayed module name (module names are not
        // translated).
        $items = [];
        $modules = \Drupal::moduleHandler()->getModuleList();
        $widgets = \Drupal::service('plugin.manager.field.widget')->getDefinitions();
        $field_types = \Drupal::service('plugin.manager.field.field_type')->getUiDefinitions();
        $formatters = \Drupal::service('plugin.manager.field.formatter')->getDefinitions();
        $providers = [];
        foreach (array_merge($field_types, $widgets, $formatters) as $plugin) {
          $providers[] = $plugin['provider'];
        }
        $providers = array_unique($providers);
        sort($providers);
        $module_extension_list = \Drupal::service('extension.list.module');
        foreach ($providers as $provider) {
          // Skip plugins provided by core components as they do not implement
          // hook_help().
          if (isset($modules[$provider])) {
            $display = $module_extension_list->getName($provider);
            if (\Drupal::moduleHandler()->hasImplementations('help', $provider)) {
              $items[] = Link::fromTextAndUrl($display, Url::fromRoute('help.page', ['name' => $provider]))->toRenderable();
            }
            else {
              $items[] = $display;
            }
          }
        }
        if ($items) {
          $output .= '<dt>' . $this->t('Provided by modules') . '</dt>';
          $output .= '<dd>' . $this->t('Here is a list of the currently installed field, formatter, and widget modules:');
          $item_list = ['#theme' => 'item_list', '#items' => $items];
          $output .= \Drupal::service('renderer')->renderInIsolation($item_list);
          $output .= '</dd>';
        }
        $output .= '<dt>' . $this->t('Provided by Drupal core') . '</dt>';
        $output .= '<dd>' . $this->t('As mentioned previously, some field types, widgets, and formatters are provided by Drupal core. Here are some notes on how to use some of these:');
        $output .= '<ul>';
        $output .= '<li><p>' . $this->t('<strong>Entity Reference</strong> fields allow you to create fields that contain links to other entities (such as content items, taxonomy terms, etc.) within the site. This allows you, for example, to include a link to a user within a content item. For more information, see <a href=":er_do">the online documentation for the Entity Reference module</a>.', [':er_do' => 'https://drupal.org/documentation/modules/entityreference']) . '</p>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Managing and displaying entity reference fields') . '</dt>';
        $output .= '<dd>' . $this->t('The <em>settings</em> and the <em>display</em> of the entity reference field can be configured separately. See the <a href=":field_ui">Field UI help</a> for more information on how to manage fields and their display.', [':field_ui' => $field_ui_url]) . '</dd>';
        $output .= '<dt>' . $this->t('Selecting reference type') . '</dt>';
        $output .= '<dd>' . $this->t('In the field settings you can select which entity type you want to create a reference to.') . '</dd>';
        $output .= '<dt>' . $this->t('Filtering and sorting reference fields') . '</dt>';
        $output .= '<dd>' . $this->t('Depending on the chosen entity type, additional filtering and sorting options are available for the list of entities that can be referred to, in the field settings. For example, the list of users can be filtered by role and sorted by name or ID.') . '</dd>';
        $output .= '<dt>' . $this->t('Displaying a reference') . '</dt>';
        $output .= '<dd>' . $this->t('An entity reference can be displayed as a simple label with or without a link to the entity. Alternatively, the referenced entity can be displayed as a teaser (or any other available view mode) inside the referencing entity.') . '</dd>';
        $output .= '<dt>' . $this->t('Configuring form displays') . '</dt>';
        $output .= '<dd>' . $this->t('Reference fields have several widgets available on the <em>Manage form display</em> page:');
        $output .= '<ul>';
        $output .= '<li>' . $this->t('The <em>Check boxes/radio buttons</em> widget displays the existing entities for the entity type as check boxes or radio buttons based on the <em>Allowed number of values</em> set for the field.') . '</li>';
        $output .= '<li>' . $this->t('The <em>Select list</em> widget displays the existing entities in a drop-down list or scrolling list box based on the <em>Allowed number of values</em> setting for the field.') . '</li>';
        $output .= '<li>' . $this->t('The <em>Autocomplete</em> widget displays text fields in which users can type entity labels based on the <em>Allowed number of values</em>. The widget can be configured to display all entities that contain the typed characters or restricted to those starting with those characters.') . '</li>';
        $output .= '<li>' . $this->t('The <em>Autocomplete (Tags style)</em> widget displays a multi-text field in which users can type in a comma-separated list of entity labels.') . '</li>';
        $output .= '</ul></dd>';
        $output .= '</dl></li>';
        $output .= '<li>' . $this->t('<strong>Number fields</strong>: When you add a number field you can choose from three types: <em>decimal</em>, <em>float</em>, and <em>integer</em>. The <em>decimal</em> number field type allows users to enter exact decimal values, with fixed numbers of decimal places. The <em>float</em> number field type allows users to enter approximate decimal values. The <em>integer</em> number field type allows users to enter whole numbers, such as years (for example, 2012) or values (for example, 1, 2, 5, 305). It does not allow decimals.') . '</li>';
        $output .= '</ul></dd>';
        $output .= '</dl>';
        return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    // Do a pass of purging on deleted Field API data, if any exists.
    $limit = \Drupal::config('field.settings')->get('purge_batch_size');
    field_purge_batch($limit);
  }

  /**
   * Implements hook_entity_field_storage_info().
   */
  #[Hook('entity_field_storage_info')]
  public function entityFieldStorageInfo(EntityTypeInterface $entity_type): array {
    if (\Drupal::entityTypeManager()->getStorage($entity_type->id()) instanceof DynamicallyFieldableEntityStorageInterface) {
      // Query by filtering on the ID as this is more efficient than filtering
      // on the entity_type property directly.
      $ids = \Drupal::entityQuery('field_storage_config')->condition('id', $entity_type->id() . '.', 'STARTS_WITH')->execute();
      // Fetch all fields and key them by field name.
      $field_storages = FieldStorageConfig::loadMultiple($ids);
      $result = [];
      foreach ($field_storages as $field_storage) {
        $result[$field_storage->getName()] = $field_storage;
      }
      return $result;
    }
    return [];
  }

  /**
   * Implements hook_entity_bundle_field_info().
   */
  #[Hook('entity_bundle_field_info')]
  public function entityBundleFieldInfo(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions): array {
    $result = [];
    if (\Drupal::entityTypeManager()->getStorage($entity_type->id()) instanceof DynamicallyFieldableEntityStorageInterface) {
      // Query by filtering on the ID as this is more efficient than filtering
      // on the entity_type property directly.
      $ids = \Drupal::entityQuery('field_config')->condition('id', $entity_type->id() . '.' . $bundle . '.', 'STARTS_WITH')->execute();
      // Fetch all fields and key them by field name.
      $field_configs = FieldConfig::loadMultiple($ids);
      foreach ($field_configs as $field_instance) {
        $result[$field_instance->getName()] = $field_instance;
      }
    }
    return $result;
  }

  /**
   * Implements hook_entity_bundle_delete().
   */
  #[Hook('entity_bundle_delete')]
  public function entityBundleDelete($entity_type_id, $bundle): void {
    $storage = \Drupal::entityTypeManager()->getStorage('field_config');
    // Get the fields on the bundle.
    $fields = $storage->loadByProperties(['entity_type' => $entity_type_id, 'bundle' => $bundle]);
    // This deletes the data for the field as well as the field themselves. This
    // function actually just marks the data and fields as deleted, leaving the
    // garbage collection for a separate process, because it is not always
    // possible to delete this much data in a single page request (particularly
    // since for some field types, the deletion is more than just a simple
    // DELETE query).
    foreach ($fields as $field) {
      $field->delete();
    }
    // We are duplicating the work done by
    // \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::onDependencyRemoval()
    // because we need to take into account bundles that are not provided by a
    // config entity type so they are not part of the config dependencies.
    // Gather a list of all entity reference fields.
    $map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('entity_reference');
    $ids = [];
    foreach ($map as $type => $info) {
      foreach ($info as $name => $data) {
        foreach ($data['bundles'] as $bundle_name) {
          $ids[] = "{$type}.{$bundle_name}.{$name}";
        }
      }
    }
    // Update the 'target_bundles' handler setting if needed.
    foreach (FieldConfig::loadMultiple($ids) as $field_config) {
      if ($field_config->getSetting('target_type') == $entity_type_id) {
        $handler_settings = $field_config->getSetting('handler_settings');
        if (isset($handler_settings['target_bundles'][$bundle])) {
          unset($handler_settings['target_bundles'][$bundle]);
          $field_config->setSetting('handler_settings', $handler_settings);
          $field_config->save();
        }
      }
    }
  }

  /**
   * @} End of "defgroup field".
   */

  /**
   * Implements hook_config_import_steps_alter().
   */
  #[Hook('config_import_steps_alter')]
  public function configImportStepsAlter(&$sync_steps, ConfigImporter $config_importer): void {
    $field_storages = ConfigImporterFieldPurger::getFieldStoragesToPurge($config_importer->getStorageComparer()->getSourceStorage()->read('core.extension'), $config_importer->getStorageComparer()->getChangelist('delete'));
    if ($field_storages) {
      // Add a step to the beginning of the configuration synchronization
      // process to purge field data where the module that provides the field is
      // being uninstalled.
      array_unshift($sync_steps, ['\Drupal\field\ConfigImporterFieldPurger', 'process']);
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Adds a warning if field data will be permanently removed by the
   * configuration synchronization.
   *
   * @see \Drupal\field\ConfigImporterFieldPurger
   */
  #[Hook('form_config_admin_import_form_alter')]
  public function formConfigAdminImportFormAlter(&$form, FormStateInterface $form_state) : void {
    // Only display the message when core.extension is available in the source
    // storage and the form is not submitted.
    $user_input = $form_state->getUserInput();
    $storage_comparer = $form_state->get('storage_comparer');
    if ($storage_comparer?->getSourceStorage()->exists('core.extension') && empty($user_input)) {
      $field_storages = ConfigImporterFieldPurger::getFieldStoragesToPurge($storage_comparer->getSourceStorage()->read('core.extension'), $storage_comparer->getChangelist('delete'));
      if ($field_storages) {
        foreach ($field_storages as $field) {
          $field_labels[] = $field->label();
        }
        \Drupal::messenger()->addWarning(\Drupal::translation()->formatPlural(count($field_storages), 'This synchronization will delete data from the field %fields.', 'This synchronization will delete data from the fields: %fields.', ['%fields' => implode(', ', $field_labels)]));
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for 'field_config'.
   */
  #[Hook('field_config_insert')]
  public function fieldConfigInsert(FieldConfigInterface $field): void {
    if ($field->isSyncing()) {
      // Don't change anything during a configuration sync.
      return;
    }
    // Allow other view modes to update their configuration for the new field.
    // Otherwise, configuration for view modes won't get updated until the mode
    // is used for the first time, creating noise in config diffs.
    \Drupal::classResolver(EntityDisplayRebuilder::class)->rebuildEntityTypeDisplays($field->getTargetEntityTypeId(), $field->getTargetBundle());
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for 'field_storage_config'.
   *
   * Reset the field handler settings, when the storage target_type is changed
   * on an entity reference field.
   */
  #[Hook('field_storage_config_update')]
  public function fieldStorageConfigUpdate(FieldStorageConfigInterface $field_storage): void {
    if ($field_storage->isSyncing()) {
      // Don't change anything during a configuration sync.
      return;
    }
    // Act on all sub-types of the entity_reference field type.
    /** @var \Drupal\Core\Field\FieldTypePluginManager $field_type_manager */
    $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
    $item_class = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';
    $class = $field_type_manager->getPluginClass($field_storage->getType());
    if ($class !== $item_class && !is_subclass_of($class, $item_class)) {
      return;
    }
    // If target_type changed, reset the handler in the fields using that
    // storage.
    if ($field_storage->getSetting('target_type') !== $field_storage->getOriginal()->getSetting('target_type')) {
      foreach ($field_storage->getBundles() as $bundle) {
        $field = FieldConfig::loadByName($field_storage->getTargetEntityTypeId(), $bundle, $field_storage->getName());
        // Reset the handler settings. This triggers
        // field_field_config_presave(), which will take care of reassigning the
        // handler to the correct derivative for the new target_type.
        $field->setSetting('handler_settings', []);
        $field->save();
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_create() for 'field_config'.
   *
   * Determine the selection handler plugin ID for an entity reference field.
   */
  #[Hook('field_config_create')]
  public function fieldConfigCreate(FieldConfigInterface $field): void {
    if ($field->isSyncing()) {
      return;
    }
    // Act on all sub-types of the entity_reference field type.
    /** @var \Drupal\Core\Field\FieldTypePluginManager $field_type_manager */
    $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
    $item_class = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';
    $class = $field_type_manager->getPluginClass($field->getType());
    if ($class !== $item_class && !is_subclass_of($class, $item_class)) {
      return;
    }
    // If we don't know the target type yet, there's nothing else we can do.
    $target_type = $field->getFieldStorageDefinition()->getSetting('target_type');
    if (empty($target_type)) {
      return;
    }
    // Make sure the selection handler plugin is the correct derivative for the
    // target entity type.
    $selection_manager = \Drupal::service('plugin.manager.entity_reference_selection');
    [$current_handler] = explode(':', $field->getSetting('handler'), 2);
    $field->setSetting('handler', $selection_manager->getPluginId($target_type, $current_handler));
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for 'field_config'.
   *
   * Determine the selection handler plugin ID for an entity reference field.
   */
  #[Hook('field_config_presave')]
  public function fieldConfigPresave(FieldConfigInterface $field): void {
    // Don't change anything during a configuration sync.
    if ($field->isSyncing()) {
      return;
    }
    $this->fieldConfigCreate($field);
    // Act on all sub-types of the entity_reference field type.
    /** @var \Drupal\Core\Field\FieldTypePluginManager $field_type_manager */
    $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
    $item_class = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';
    $class = $field_type_manager->getPluginClass($field->getType());
    if ($class !== $item_class && !is_subclass_of($class, $item_class)) {
      return;
    }
    // In case we removed all the target bundles allowed by the field in
    // EntityReferenceItem::onDependencyRemoval() or
    // field_entity_bundle_delete() we have to log a critical message because
    // the field will not function correctly anymore.
    $handler_settings = $field->getSetting('handler_settings');
    if (isset($handler_settings['target_bundles']) && $handler_settings['target_bundles'] === []) {
      \Drupal::logger('entity_reference')->critical('The %field_name entity reference field (entity_type: %entity_type, bundle: %bundle) no longer has any valid bundle it can reference. The field is not working correctly anymore and has to be adjusted.', [
        '%field_name' => $field->getName(),
        '%entity_type' => $field->getTargetEntityTypeId(),
        '%bundle' => $field->getTargetBundle(),
      ]);
    }
  }

}
