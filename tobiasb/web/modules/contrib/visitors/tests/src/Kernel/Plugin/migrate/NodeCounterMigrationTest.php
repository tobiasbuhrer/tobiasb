<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Kernel\Plugin\migrate;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\NodeType;
use Drupal\visitors\Plugin\migrate\destination\VisitorsCounter;
use Drupal\visitors\Plugin\migrate\process\NodeCounterId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests importing Statistics node counts into visitors_counter.
 *
 * @group visitors
 */
#[CoversClass(VisitorsCounter::class)]
#[CoversClass(NodeCounterId::class)]
#[Group('visitors')]
#[RunTestsInSeparateProcesses]
class NodeCounterMigrationTest extends KernelTestBase implements MigrateMessageInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'path_alias',
    'views',
    'migrate',
    'visitors',
    'visitors_migrate_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['node', 'filter', 'user']);
    $this->installSchema('visitors', ['visitors_counter']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    $schema = $this->container->get('database')->schema();
    $schema->createTable('node_counter', [
      'fields' => [
        'nid' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'totalcount' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'size' => 'big',
          'default' => 0,
        ],
        'daycount' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'size' => 'medium',
          'default' => 0,
        ],
        'timestamp' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
      ],
      'primary key' => ['nid'],
    ]);
  }

  /**
   * Copies node IDs when no node migrations are configured.
   */
  public function testPreservesNodeIds(): void {
    $this->insertCount(5, 10, 3, 1000);
    $this->insertCount(8, 4, 1, 2000);

    $migration = $this->statisticsMigration();
    $this->import($migration);

    $this->assertCounter(5, 10, 3, 1000);
    $this->assertCounter(8, 4, 1, 2000);

    $this->container->get('database')->update('node_counter')
      ->fields([
        'totalcount' => 25,
        'daycount' => 6,
        'timestamp' => 3000,
      ])
      ->condition('nid', 5)
      ->execute();

    $migration = $this->statisticsMigration();
    $migration->getIdMap()->prepareUpdate();
    $this->import($migration);
    $this->assertCounter(5, 25, 6, 3000);

    $this->container->get('database')->insert('visitors_counter')
      ->fields([
        'entity_type' => 'node',
        'entity_id' => 99,
        'total' => 7,
        'today' => 1,
        'timestamp' => 4000,
      ])
      ->execute();

    $migration = $this->statisticsMigration();
    (new MigrateExecutable($migration, $this))->rollback();

    $this->assertNull($this->counter(5));
    $this->assertNull($this->counter(8));
    $this->assertCounter(99, 7, 1, 4000);
  }

  /**
   * Looks up destination node IDs and skips nodes that were not migrated.
   */
  public function testLooksUpNodeIds(): void {
    $node_migration = $this->container->get('plugin.manager.migration')
      ->createInstance('visitors_migrate_test_node');
    $this->import($node_migration);

    $this->insertCount(10, 15, 2, 5000);
    $this->insertCount(99, 8, 1, 6000);

    $migration = $this->statisticsMigration(['visitors_migrate_test_node']);
    $this->import($migration);

    $this->assertCounter(42, 15, 2, 5000);
    $this->assertNull($this->counter(10));
    $this->assertNull($this->counter(99));

    $row = $migration->getIdMap()->getRowBySource(['nid' => 99]);
    $this->assertSame(
      MigrateIdMapInterface::STATUS_IGNORED,
      (int) $row['source_row_status']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function display($message, $type = 'status') {
    // The migration result is asserted by the caller.
  }

  /**
   * Builds the statistics migration against the test database.
   *
   * @param string[] $node_migrations
   *   Node migration IDs to look up. Empty copies node IDs unchanged.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface
   *   The migration.
   */
  protected function statisticsMigration(array $node_migrations = []): MigrationInterface {
    $migration = $this->container->get('plugin.manager.migration')
      ->createInstance('visitors_statistics_node_counter');
    $source = $migration->getSourceConfiguration();
    $source['key'] = 'default';
    $source['node_migrations'] = $node_migrations;
    $migration->set('source', $source);
    return $migration;
  }

  /**
   * Imports a migration.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   */
  protected function import(MigrationInterface $migration): void {
    $result = (new MigrateExecutable($migration, $this))->import();
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, $result);
  }

  /**
   * Inserts a Statistics node counter row.
   */
  protected function insertCount(int $nid, int $total, int $today, int $timestamp): void {
    $this->container->get('database')->insert('node_counter')
      ->fields([
        'nid' => $nid,
        'totalcount' => $total,
        'daycount' => $today,
        'timestamp' => $timestamp,
      ])
      ->execute();
  }

  /**
   * Asserts a visitors_counter row.
   */
  protected function assertCounter(int $entity_id, int $total, int $today, int $timestamp): void {
    $row = $this->counter($entity_id);
    $this->assertNotNull($row);
    $this->assertSame('node', $row['entity_type']);
    $this->assertSame($entity_id, (int) $row['entity_id']);
    $this->assertSame($total, (int) $row['total']);
    $this->assertSame($today, (int) $row['today']);
    $this->assertSame($timestamp, (int) $row['timestamp']);
  }

  /**
   * Loads a visitors_counter row.
   *
   * @return array<string, mixed>|null
   *   The row, or NULL when it does not exist.
   */
  protected function counter(int $entity_id): ?array {
    $row = $this->container->get('database')->select('visitors_counter', 'c')
      ->fields('c')
      ->condition('entity_type', 'node')
      ->condition('entity_id', $entity_id)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

}
