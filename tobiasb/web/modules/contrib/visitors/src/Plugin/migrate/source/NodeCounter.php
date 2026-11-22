<?php

declare(strict_types=1);

namespace Drupal\visitors\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Reads node view counts from the Statistics node_counter table.
 *
 * The connection is the SqlBase key. It defaults to migrate.
 *
 * Available configuration keys:
 * - node_migrations: (optional) Node migration IDs. When this is empty, node
 *   IDs are copied unchanged. When it is set, the visitors_node_counter_id
 *   process plugin looks up the destination node ID.
 *
 * @MigrateSource(
 *   id = "visitors_node_counter"
 * )
 */
class NodeCounter extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('node_counter', 'nc')->fields('nc');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'nid' => $this->t('The node ID.'),
      'totalcount' => $this->t('The total number of times the node has been viewed.'),
      'daycount' => $this->t('The total number of times the node has been viewed today.'),
      'timestamp' => $this->t('The most recent time the node has been viewed.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
        'type' => 'integer',
      ],
    ];
  }

}
