<?php

declare(strict_types=1);

namespace Drupal\visitors\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * Implements counter hooks for nodes and entities.
 */
class CounterHooks {

  /**
   * Implements hook_node_links_alter().
   */
  #[Hook('node_links_alter')]
  public static function nodeLinksAlter(array &$links, NodeInterface $entity, array &$context) {
    if ($context['view_mode'] == 'rss') {
      return NULL;
    }
    $links['#cache']['contexts'][] = 'user.permissions';
    if (!\Drupal::currentUser()->hasPermission('view visitors counter')) {
      return NULL;
    }
    $settings = \Drupal::config('visitors.config');
    $statistics = \Drupal::service('visitors.counter')->fetchView('node', (int) $entity->id());
    if ($statistics) {
      $statistics_links['visitors_counter']['title'] = \Drupal::translation()->formatPlural($statistics->getTotalCount(), '1 view', '@count views');
      $links['visitors'] = [
        '#theme' => 'links__node__visitors',
        '#links' => $statistics_links,
        '#attributes' => [
          'class' => [
            'links',
            'inline',
          ],
        ],
      ];
    }
    $links['#cache']['max-age'] = $settings->get('counter.display_max_age');
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public static function entityDelete(EntityInterface $entity) {
    $entity_id = $entity->id();
    if (!is_int($entity_id)) {
      return;
    }
    $entity_type = $entity->getEntityTypeId();
    \Drupal::service('visitors.counter')->deleteViews($entity_type, $entity_id);
  }

}
