<?php

declare(strict_types=1);

namespace Drupal\visitors\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements hook_views_data().
 */
class ViewsHooks implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ViewsHooks object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory     = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData() {
    $data = [];
    $data['visitors_counter']['table']['group'] = $this->t('Visitor counters');
    $data['visitors_counter']['table']['base'] = [
      'title' => $this->t('Visitor Counters'),
      'help' => $this->t('Visitors data from visitors DB table.'),
    ];
    $settings = $this->configFactory->get('visitors.config');
    $supported_entity_types = $settings->get('counter.entity_types') ?? [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      $base_table = $entity_type->getBaseTable();
      if (!in_array($entity_type_id, $supported_entity_types) || !$entity_type->entityClassImplements(ContentEntityInterface::class) || !$base_table) {
        continue;
      }
      $base_table = $entity_type->getDataTable() ?: $base_table;
      $args = [
        '@entity_type' => $entity_type_id,
      ];
      // Multilingual properties are stored in data table.
      if (!$table = $entity_type->getDataTable()) {
        $table = $base_table;
      }
      $data[$base_table]['visitors_counter'] = [
        'title' => $this->t('Visitors @entity_type counter', $args),
        'help' => $this->t('Relate all visitor counts on the @entity_type.', $args),
        'relationship' => [
          'group' => $this->t('Visitor Counters'),
          'label' => $this->t('Visitor counters'),
          'base' => 'visitors_counter',
          'base field' => 'entity_id',
          'relationship field' => $entity_type->getKey('id'),
          'id' => 'standard',
          'extra' => [
                    [
                      'field' => 'entity_type',
                      'value' => $entity_type_id,
                    ],
          ],
        ],
      ];
      $data['visitors_counter']['table']['join'][$table] = [
        'type' => 'LEFT',
        'left_field' => $entity_type->getKey('id'),
        'field' => 'entity_id',
        'extra' => [
                [
                  'field' => 'entity_type',
                  'value' => $entity_type_id,
                ],
        ],
      ];
    }
    $data['visitors_counter']['total'] = [
      'title' => $this->t('Total views'),
      'help' => $this->t('The total number of times the node has been viewed.'),
      'field' => [
        'id' => 'visitors_numeric',
        'click sortable' => TRUE,
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
    $data['visitors_counter']['today'] = [
      'title' => $this->t('Views today'),
      'help' => $this->t('The total number of times the node has been viewed today.'),
      'field' => [
        'id' => 'visitors_numeric',
        'click sortable' => TRUE,
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
    $data['visitors_counter']['timestamp'] = [
      'title' => $this->t('Most recent visit'),
      'help' => $this->t('The most recent time the node has been viewed.'),
      'field' => [
        'id' => 'visitors_counter_timestamp',
        'click sortable' => TRUE,
      ],
      'filter' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
    $data['visitors']['table']['group'] = $this->t('Visitors');
    $data['visitors']['table']['base'] = [
      'title' => $this->t('Visitors'),
      'help' => $this->t('Visitors data from visitors DB table.'),
    ];
    $data['visitors']['visitors_id'] = [
      'title' => $this->t('Visitors ID'),
      'help' => $this->t('Visitors entry ID.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['visitors']['visitor_id'] = [
      'title' => $this->t('Unique visitor'),
      'help' => $this->t('A unique ID for the visitor.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['visitors_uid'] = [
      'title' => $this->t('Visitors UID'),
      'help' => $this->t('The user ID of the visitors entry.'),
      'field' => [
        'id' => 'standard',
      ],
      'relationship' => [
        'title' => $this->t('User'),
        'help' => $this->t('The user entity from the visitor entry.'),
        'base' => 'users_field_data',
        'base field' => 'uid',
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['visitors']['visitors_date_time'] = [
      'title' => $this->t('Visitors Date Time'),
      'help' => $this->t('The timestamp from the visitors entry.'),
      'field' => [
        'id' => 'date',
        'click sortable' => TRUE,
      ],
      'filter' => [
        'id' => 'visitors_date',
      ],
    ];
    $data['visitors']['visitors_hour'] = [
      'title' => $this->t('Hour'),
      'help' => $this->t('The hour (server) of the visit.'),
      'field' => [
        'id' => 'visitors_hour',
        'field' => 'visitors_date_time',
      ],
      'sort' => [
        'id' => 'visitors_timestamp',
        'field' => 'visitors_date_time',
      ],
    ];
    $data['visitors']['visitors_month'] = [
      'title' => $this->t('Month'),
      'help' => $this->t('The month of the visit.'),
      'field' => [
        'id' => 'visitors_month',
        'field' => 'visitors_date_time',
      ],
      'sort' => [
        'id' => 'visitors_timestamp',
        'field' => 'visitors_date_time',
      ],
    ];
    $data['visitors']['visitors_day_of_week'] = [
      'title' => $this->t('Day of Week'),
      'help' => $this->t('The day of week of the visit.'),
      'field' => [
        'id' => 'visitors_day_of_week',
        'field' => 'visitors_date_time',
      ],
      'sort' => [
        'id' => 'visitors_timestamp',
        'field' => 'visitors_date_time',
      ],
    ];
    $data['visitors']['visitors_day_of_month'] = [
      'title' => $this->t('Day of Month'),
      'help' => $this->t('The day of month of the visit.'),
      'field' => [
        'id' => 'visitors_day_of_month',
        'field' => 'visitors_date_time',
      ],
      'sort' => [
        'id' => 'visitors_timestamp',
        'field' => 'visitors_date_time',
      ],
    ];
    $data['visitors']['visitors_day'] = [
      'title' => $this->t('Day'),
      'help' => $this->t('The day of the visit.'),
      'field' => [
        'id' => 'visitors_day',
        'field' => 'visitors_date_time',
      ],
      'sort' => [
        'id' => 'visitors_timestamp',
        'field' => 'visitors_date_time',
      ],
    ];
    $data['visitors']['visitors_week'] = [
      'title' => $this->t('Week'),
      'help' => $this->t('The week of the visit.'),
      'field' => [
        'id' => 'visitors_week',
        'field' => 'visitors_date_time',
      ],
      'sort' => [
        'id' => 'visitors_timestamp',
        'field' => 'visitors_date_time',
      ],
    ];
    $data['visitors']['visitor_localtime'] = [
      'title' => $this->t('Visitor Hour'),
      'help' => $this->t('The hour (client) of the visit.'),
      'field' => [
        'id' => 'visitors_local_hour',
        'field' => 'visitor_localtime',
      ],
      'sort' => [
        'id' => 'visitors_timestamp',
        'field' => 'visitors_date_time',
      ],
    ];
    $data['visitors']['visitors_ip'] = [
      'title' => $this->t('Visitors IP'),
      'help' => $this->t('The IP of the visitors entry.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['server'] = [
      'title' => $this->t('Server'),
      'help' => $this->t('The server that generated the response.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['visitors_url'] = [
      'title' => $this->t('Visitors URL'),
      'help' => $this->t('The URL of the visitors entry.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['visitors_referer'] = [
      'title' => $this->t('Visitors referer'),
      'help' => $this->t('The referer of the visitors entry.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['visitors_path'] = [
      'title' => $this->t('Visitors path'),
      'help' => $this->t('The path of the visitors entry.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['route'] = [
      'title' => $this->t('Route'),
      'help' => $this->t('The route of the visitors entry.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['visitors_title'] = [
      'title' => $this->t('Visitors title'),
      'help' => $this->t('The title of the visitors entry.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['visitors_user_agent'] = [
      'title' => $this->t('Visitors user agent'),
      'help' => $this->t('The user agent of the visitors entry.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['config_resolution'] = [
      'title' => $this->t('Resolution'),
      'help' => $this->t("The visitor's screen resolution."),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['config_pdf'] = [
      'title' => $this->t('PDF Plugin'),
      'help' => $this->t("The visitor's browser supports PDFs."),
      'field' => [
        'id' => 'visitors_pdf',
      ],
      'filter' => [
        'id' => 'boolean',
      ],
    ];
    $data['visitors']['config_flash'] = [
      'title' => $this->t('Flash Plugin'),
      'help' => $this->t("The visitor's browser supports Flash."),
      'field' => [
        'id' => 'visitors_flash',
      ],
      'filter' => [
        'id' => 'boolean',
      ],
    ];
    $data['visitors']['config_java'] = [
      'title' => $this->t('Java Plugin'),
      'help' => $this->t("The visitor's browser supports Java."),
      'field' => [
        'id' => 'visitors_java',
      ],
      'filter' => [
        'id' => 'boolean',
      ],
    ];
    $data['visitors']['config_quicktime'] = [
      'title' => $this->t('Quicktime Plugin'),
      'help' => $this->t("The visitor's browser supports Quicktime."),
      'field' => [
        'id' => 'visitors_quicktime',
      ],
      'filter' => [
        'id' => 'boolean',
      ],
    ];
    $data['visitors']['config_realplayer'] = [
      'title' => $this->t('Realplayer Plugin'),
      'help' => $this->t("The visitor's browser supports Realplayer."),
      'field' => [
        'id' => 'visitors_realplayer',
      ],
      'filter' => [
        'id' => 'boolean',
      ],
    ];
    $data['visitors']['config_windowsmedia'] = [
      'title' => $this->t('Windows Media Plugin'),
      'help' => $this->t("The visitor's browser supports Windows Media."),
      'field' => [
        'id' => 'visitors_windowsmedia',
      ],
      'filter' => [
        'id' => 'boolean',
      ],
    ];
    $data['visitors']['config_silverlight'] = [
      'title' => $this->t('Silverlight Plugin'),
      'help' => $this->t("The visitor's browser supports Silverlight."),
      'field' => [
        'id' => 'visitors_silverlight',
      ],
      'filter' => [
        'id' => 'boolean',
      ],
    ];
    $data['visitors']['config_cookie'] = [
      'title' => $this->t('Cookie Plugin'),
      'help' => $this->t("The visitor's browser supports cookies."),
      'field' => [
        'id' => 'visitors_cookie',
      ],
      'filter' => [
        'id' => 'boolean',
      ],
    ];
    $data['visitors']['config_browser_engine'] = [
      'title' => $this->t('Browser Engine'),
      'help' => $this->t('The engine used by the browser.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['config_browser_name'] = [
      'title' => $this->t('Browser Name'),
      'help' => $this->t('The name of the browser.'),
      'field' => [
        'id' => 'visitors_browser',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['config_browser_version'] = [
      'title' => $this->t('Browser Version'),
      'help' => $this->t('The version of the browser.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['config_client_type'] = [
      'title' => $this->t('Client type'),
      'help' => $this->t('The type of the client.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['config_device_brand'] = [
      'title' => $this->t('Device brand'),
      'help' => $this->t('The brand of the device.'),
      'field' => [
        'id' => 'visitors_brand',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['config_device_model'] = [
      'title' => $this->t('Device model'),
      'help' => $this->t('The model of the device.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['config_device_type'] = [
      'title' => $this->t('Device type'),
      'help' => $this->t('The type of device.'),
      'field' => [
        'id' => 'visitors_device',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['config_os'] = [
      'title' => $this->t('Operating System'),
      'help' => $this->t('The operating system.'),
      'field' => [
        'id' => 'visitors_operating_system',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['config_os_version'] = [
      'title' => $this->t('OS version'),
      'help' => $this->t('The version of the Operating System.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['bot'] = [
      'title' => $this->t('Bot'),
      'help' => $this->t("The visit is from a bot."),
      'field' => [
        'id' => 'boolean',
      ],
      'filter' => [
        'id' => 'boolean',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['visitors']['language'] = [
      'title' => $this->t('Language'),
      'help' => $this->t('The browser language.'),
      'field' => [
        'id' => 'visitors_language',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['location_continent'] = [
      'title' => $this->t('Continent'),
      'help' => $this->t('The location continent.'),
      'field' => [
        'id' => 'visitors_continent',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['location_country'] = [
      'title' => $this->t('Country'),
      'help' => $this->t('The location country.'),
      'field' => [
        'id' => 'visitors_country',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['pf_network'] = [
      'title' => $this->t('Network'),
      'help' => $this->t('Network performance.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['visitors']['pf_server'] = [
      'title' => $this->t('Server'),
      'help' => $this->t('Server performance.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['visitors']['pf_transfer'] = [
      'title' => $this->t('Transfer'),
      'help' => $this->t('Transfer performance.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['visitors']['pf_dom_processing'] = [
      'title' => $this->t('DOM Processing'),
      'help' => $this->t('DOM Processing performance.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['visitors']['pf_dom_complete'] = [
      'title' => $this->t('DOM Complete'),
      'help' => $this->t('DOM Complete performance.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['visitors']['pf_on_load'] = [
      'title' => $this->t('On Load'),
      'help' => $this->t('On Load performance.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['visitors']['pf_total'] = [
      'title' => $this->t('Total'),
      'help' => $this->t('Total performance.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['visitors']['visitors_display_link'] = [
      'title' => $this->t('Link to Visitors display'),
      'help' => $this->t('Displays a link to a non-path-based display of this view while keeping the filter criteria, sort criteria, pager settings and contextual filters.'),
      'area' => [
        'id' => 'visitors_display_link',
      ],
    ];
    return $data;
  }

}
