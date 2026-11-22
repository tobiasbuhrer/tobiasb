<?php

declare(strict_types=1);

namespace Drupal\visitors\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements search ranking hooks.
 */
class SearchHooks implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a SearchHooks object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state) {
    $this->configFactory = $config_factory;
    $this->state         = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('config.factory'),
      $container->get('state'),
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * Implements hook_ranking() and hook_node_search_ranking().
   *
   * Hook_ranking() is used by the base search module (pre-Drupal 11.4).
   * hook_node_search_ranking() is used by the search_node submodule
   * introduced in Drupal 11.4.
   */
  #[Hook('ranking')]
  #[Hook('node_search_ranking')]
  public function ranking() {
    $settings = $this->configFactory->get('visitors.config');
    $is_enabled_and_has_node_entity_type = $settings->get('counter.enabled') && in_array('node', $settings->get('counter.entity_types'));
    if ($is_enabled_and_has_node_entity_type) {
      return [
        'views' => [
          'title' => $this->t('Number of views'),
          'join' => [
            'type' => 'LEFT',
            'table' => 'visitors_counter',
            'alias' => 'visitors_counter',
            'on' => "visitors_counter.entity_id = i.sid AND visitors_counter.entity_type = 'node'",
          ],
          // Inverse law that maps the highest view count on the site
          // to 1 and 0 to 0. Note that the ROUND here is necessary for
          // PostgreSQL and SQLite in order to ensure that the
          // :statistics_scale argument is treated as a numeric type,
          // because the PostgreSQL PDO driver sometimes puts values in
          // as strings instead of numbers in complex expressions like
          // this.
          'score' => '2.0 - 2.0 / (1.0 + visitors_counter.total * (ROUND(:statistics_scale, 4)))',
          'arguments' => [
            ':statistics_scale' => $this->state->get('visitors.node_counter_scale', 0),
          ],
        ],
      ];
    }
  }

}
