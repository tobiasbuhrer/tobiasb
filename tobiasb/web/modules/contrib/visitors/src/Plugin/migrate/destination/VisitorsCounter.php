<?php

declare(strict_types=1);

namespace Drupal\visitors\Plugin\migrate\destination;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Writes Statistics node counts into visitors_counter.
 *
 * A later import replaces the counts for that entity. Run this migration
 * before the new site records its own views.
 *
 * @MigrateDestination(
 *   id = "visitors_counter"
 * )
 */
class VisitorsCounter extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a VisitorsCounter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    protected Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->supportsRollback = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    $entity_type = (string) $row->getDestinationProperty('entity_type');
    $entity_id = (int) $row->getDestinationProperty('entity_id');
    $this->database->merge('visitors_counter')
      ->keys([
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
      ])
      ->fields([
        'total' => (int) $row->getDestinationProperty('total'),
        'today' => (int) $row->getDestinationProperty('today'),
        'timestamp' => (int) $row->getDestinationProperty('timestamp'),
      ])
      ->execute();

    return [$entity_type, $entity_id];
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(array $destination_identifier) {
    $this->database->delete('visitors_counter')
      ->condition('entity_type', $destination_identifier['entity_type'])
      ->condition('entity_id', $destination_identifier['entity_id'])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'entity_type' => [
        'type' => 'string',
        'max_length' => EntityTypeInterface::ID_MAX_LENGTH,
      ],
      'entity_id' => [
        'type' => 'integer',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'entity_type' => $this->t('The entity type.'),
      'entity_id' => $this->t('The entity ID.'),
      'total' => $this->t('The total number of views.'),
      'today' => $this->t('The number of views today.'),
      'timestamp' => $this->t('The time of the most recent view.'),
    ];
  }

}
