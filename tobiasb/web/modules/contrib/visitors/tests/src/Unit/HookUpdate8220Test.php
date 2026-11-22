<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Unit;

use Drupal\Core\Database\Query\Condition;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\Group;

require_once __DIR__ . '/../../../visitors.install';

/**
 * Tests visitors_update_8220().
 */
#[Group('visitors')]
class HookUpdate8220Test extends UnitTestCase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The settings config.
   *
   * @var \Drupal\Core\Config\Config|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $settings;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleInstaller;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $this->configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $container->set('config.factory', $this->configFactory);

    $this->settings = $this->createMock('Drupal\Core\Config\Config');

    $this->moduleHandler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $container->set('module_handler', $this->moduleHandler);

    $this->moduleInstaller = $this->createMock('Drupal\Core\Extension\ModuleInstallerInterface');
    $container->set('module_installer', $this->moduleInstaller);

    $this->database = $this->createMock('Drupal\Core\Database\Connection');
    $container->set('database', $this->database);

    $this->entityTypeManager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $container->set('entity_type.manager', $this->entityTypeManager);

    \Drupal::setContainer($container);
  }

  /**
   * Location column spec used by visitors_update_8220().
   *
   * @return array<string, mixed>
   *   The field spec.
   */
  private function locationSpec(): array {
    return [
      'type'     => 'varchar',
      'length'   => 2,
      'not null' => TRUE,
      'default'  => '',
    ];
  }

  /**
   * Runs the shared config, module, view, and block expectations.
   *
   * @param \Drupal\Core\Database\Schema|\PHPUnit\Framework\MockObject\MockObject $schema
   *   The schema mock returned by the database connection.
   * @param bool $delete_block
   *   TRUE when an existing visitors block should be deleted.
   */
  private function expectUpdateFramework(object $schema, bool $delete_block): void {
    $this->configFactory->expects($this->once())
      ->method('getEditable')
      ->with('visitors.config')
      ->willReturn($this->settings);

    $this->settings->expects($this->exactly(2))
      ->method('clear')
      ->willReturnMap([
        ['chart_height', $this->settings],
        ['chart_width', $this->settings],
      ]);
    $this->settings->expects($this->once())
      ->method('save');

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('charts_chartjs')
      ->willReturn(FALSE);

    $this->moduleInstaller->expects($this->once())
      ->method('install')
      ->with(['charts_chartjs']);

    $schema->expects($this->once())
      ->method('tableExists')
      ->with('visitors_performance')
      ->willReturn(FALSE);
    $schema->expects($this->once())
      ->method('createTable');

    $this->database->expects($this->once())
      ->method('schema')
      ->willReturn($schema);

    $visitors_view = $this->createMock('Drupal\views\Entity\View');
    $visitors_view->expects($this->once())
      ->method('save');
    $old_view = $this->createMock('Drupal\views\Entity\View');
    $old_view->expects($this->exactly(5))
      ->method('delete');
    $view_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $view_storage->expects($this->once())
      ->method('create')
      ->willReturn($visitors_view);
    $view_storage->expects($this->exactly(5))
      ->method('load')
      ->willReturn($old_view);

    $block_query = $this->createMock('Drupal\Core\Entity\Query\QueryInterface');
    $block_query->expects($this->once())
      ->method('condition')
      ->with('plugin', 'visitors_block')
      ->willReturnSelf();
    $block_query->expects($this->once())
      ->method('accessCheck')
      ->with(FALSE)
      ->willReturnSelf();
    $block_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $block_storage->expects($this->once())
      ->method('getQuery')
      ->willReturn($block_query);

    if ($delete_block) {
      $block_query->expects($this->once())
        ->method('execute')
        ->willReturn(['visitors_block']);
      $visitors_block = $this->createMock('Drupal\block\Entity\Block');
      $visitors_block->expects($this->once())
        ->method('delete');
      $block_storage->expects($this->once())
        ->method('load')
        ->with('visitors_block')
        ->willReturn($visitors_block);
    }
    else {
      $block_query->expects($this->once())
        ->method('execute')
        ->willReturn([]);
    }

    $this->entityTypeManager->expects($this->exactly(2))
      ->method('getStorage')
      ->willReturnMap([
        ['view', $view_storage],
        ['block', $block_storage],
      ]);
  }

  /**
   * Stubs fieldExists() and records later schema changes.
   *
   * @param \Drupal\Core\Database\Schema|\PHPUnit\Framework\MockObject\MockObject $schema
   *   The schema mock.
   * @param array<string, bool> $fields
   *   Field name to fieldExists() result.
   * @param array<int, array<int, mixed>> $checked
   *   Recorded fieldExists() field names.
   * @param array<int, array<int, mixed>> $added
   *   Recorded addField() arguments.
   * @param array<int, array<int, mixed>> $changed
   *   Recorded changeField() arguments.
   * @param array<int, array<int, mixed>> $dropped
   *   Recorded dropField() arguments.
   */
  private function recordSchema(
    object $schema,
    array $fields,
    array &$checked,
    array &$added,
    array &$changed,
    array &$dropped,
  ): void {
    $schema->method('fieldExists')
      ->willReturnCallback(function (string $table, string $field) use ($fields, &$checked): bool {
        $this->assertSame('visitors', $table);
        $this->assertArrayHasKey($field, $fields);
        $checked[] = $field;
        return $fields[$field];
      });
    $schema->method('addField')
      ->willReturnCallback(function (string $table, string $field, array $spec) use (&$added): void {
        $added[] = [$table, $field, $spec];
      });
    $schema->method('changeField')
      ->willReturnCallback(function (string $table, string $field, string $field_new, array $spec) use (&$changed): void {
        $changed[] = [$table, $field, $field_new, $spec];
      });
    $schema->method('dropField')
      ->willReturnCallback(function (...$args) use (&$dropped): void {
        $dropped[] = $args;
      });
  }

  /**
   * Tests visitors_update_8220 when location columns are missing.
   */
  public function testUpdate8220WithoutGeoIp(): void {
    $schema = $this->createMock('Drupal\Core\Database\Schema');
    $checked = [];
    $added = [];
    $changed = [];
    $dropped = [];
    $this->recordSchema($schema, [
      'language' => FALSE,
      'location_continent_code' => FALSE,
      'location_continent' => FALSE,
      'location_country_code' => FALSE,
      'location_country' => FALSE,
    ], $checked, $added, $changed, $dropped);
    $this->database->expects($this->never())
      ->method('update');
    $this->expectUpdateFramework($schema, FALSE);

    visitors_update_8220();

    $spec = $this->locationSpec();
    $this->assertSame([
      'language',
      'location_continent_code',
      'location_continent',
      'location_country_code',
      'location_country',
    ], $checked);
    $this->assertSame([
      ['visitors', 'language', $spec],
      ['visitors', 'location_continent', $spec],
      ['visitors', 'location_country', $spec],
    ], $added);
    $this->assertSame([], $changed);
    $this->assertSame([], $dropped);
  }

  /**
   * Tests visitors_update_8220 renaming GeoIP *_code columns.
   */
  public function testUpdate8220WithGeoIp(): void {
    $schema = $this->createMock('Drupal\Core\Database\Schema');
    $checked = [];
    $added = [];
    $changed = [];
    $dropped = [];
    $this->recordSchema($schema, [
      'language' => FALSE,
      'location_continent_code' => TRUE,
      'location_continent' => FALSE,
      'location_country_code' => TRUE,
      'location_country' => FALSE,
    ], $checked, $added, $changed, $dropped);
    $this->database->expects($this->never())
      ->method('update');
    $this->expectUpdateFramework($schema, TRUE);

    visitors_update_8220();

    $spec = $this->locationSpec();
    $this->assertSame([
      ['visitors', 'language', $spec],
    ], $added);
    $this->assertSame([
      ['visitors', 'location_continent_code', 'location_continent', $spec],
      ['visitors', 'location_country_code', 'location_country', $spec],
    ], $changed);
    $this->assertSame([], $dropped);
  }

  /**
   * Tests a rerun where the current location columns already exist.
   */
  public function testUpdate8220LocationColumnsAlreadyExist(): void {
    $schema = $this->createMock('Drupal\Core\Database\Schema');
    $checked = [];
    $added = [];
    $changed = [];
    $dropped = [];
    $this->recordSchema($schema, [
      'language' => TRUE,
      'location_continent_code' => FALSE,
      'location_continent' => TRUE,
      'location_country_code' => FALSE,
      'location_country' => TRUE,
    ], $checked, $added, $changed, $dropped);
    $this->database->expects($this->never())
      ->method('update');
    $this->expectUpdateFramework($schema, FALSE);

    visitors_update_8220();

    $this->assertSame([], $added);
    $this->assertSame([], $changed);
    $this->assertSame([], $dropped);
  }

  /**
   * Tests merging when both the legacy and current columns exist.
   */
  public function testUpdate8220BothLocationColumns(): void {
    $schema = $this->createMock('Drupal\Core\Database\Schema');
    $checked = [];
    $added = [];
    $changed = [];
    $dropped = [];
    $this->recordSchema($schema, [
      'language' => TRUE,
      'location_continent_code' => TRUE,
      'location_continent' => TRUE,
      'location_country_code' => TRUE,
      'location_country' => TRUE,
    ], $checked, $added, $changed, $dropped);

    $expressions = [];
    $conditions = [];
    $update = $this->createMock('Drupal\Core\Database\Query\Update');
    $update->expects($this->exactly(2))
      ->method('expression')
      ->willReturnCallback(function (string $field, string $expression) use (&$expressions, $update) {
        $expressions[] = [$field, $expression];
        return $update;
      });
    $update->expects($this->exactly(2))
      ->method('condition')
      ->willReturnCallback(function ($condition) use (&$conditions, $update) {
        $conditions[] = $condition;
        return $update;
      });
    $update->expects($this->exactly(2))
      ->method('execute');
    $this->database->expects($this->exactly(2))
      ->method('update')
      ->with('visitors')
      ->willReturn($update);
    $this->expectUpdateFramework($schema, FALSE);

    visitors_update_8220();

    $this->assertSame([], $added);
    $this->assertSame([], $changed);
    $this->assertSame([
      ['visitors', 'location_continent_code'],
      ['visitors', 'location_country_code'],
    ], $dropped);
    $this->assertSame([
      ['location_continent', 'location_continent_code'],
      ['location_country', 'location_country_code'],
    ], $expressions);
    $this->assertCount(2, $conditions);
    $this->assertEquals($this->blankCondition('location_continent'), $conditions[0]);
    $this->assertEquals($this->blankCondition('location_country'), $conditions[1]);
  }

  /**
   * Builds the OR condition that selects blank location values.
   *
   * @param string $field
   *   The current location column.
   *
   * @return \Drupal\Core\Database\Query\Condition
   *   The condition.
   */
  private function blankCondition(string $field): Condition {
    $blank = new Condition('OR');
    $blank->condition($field, '');
    $blank->condition($field, NULL, 'IS NULL');
    return $blank;
  }

}
