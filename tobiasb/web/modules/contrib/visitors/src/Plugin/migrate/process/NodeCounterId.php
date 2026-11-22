<?php

declare(strict_types=1);

namespace Drupal\visitors\Plugin\migrate\process;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Maps a Statistics node ID to the destination node ID.
 *
 * When the source configuration node_migrations is empty, the source ID is
 * returned. Otherwise the ID is looked up in those migrations. A node that
 * was not migrated is skipped.
 *
 * @MigrateProcessPlugin(
 *   id = "visitors_node_counter_id"
 * )
 */
class NodeCounterId extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a NodeCounterId object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   * @param \Drupal\migrate\MigrateLookupInterface $migrateLookup
   *   The migrate lookup service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected MigrationInterface $migration,
    protected MigrateLookupInterface $migrateLookup,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('migrate.lookup'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $migration_ids = $this->migration->getSourceConfiguration()['node_migrations'] ?? [];
    if ($migration_ids === [] || $migration_ids === '') {
      return (int) $value;
    }

    foreach ((array) $migration_ids as $migration_id) {
      try {
        $matches = $this->migrateLookup->lookup($migration_id, [$value]);
      }
      catch (PluginNotFoundException $e) {
        throw new MigrateException(sprintf('Node migration "%s" was not found.', $migration_id), 0, $e);
      }
      if ($matches) {
        $ids = array_values(reset($matches));
        return (int) reset($ids);
      }
    }

    throw new MigrateSkipRowException(sprintf('Node %s was not migrated.', $value));
  }

}
