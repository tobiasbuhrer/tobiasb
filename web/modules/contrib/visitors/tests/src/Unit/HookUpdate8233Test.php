<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once __DIR__ . '/../../../visitors.install';

/**
 * Tests visitors_update_8233().
 *
 * @group visitors
 */
final class HookUpdate8233Test extends UnitTestCase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $this->database = $this->createMock('Drupal\Core\Database\Connection');
    $container->set('database', $this->database);

    \Drupal::setContainer($container);
  }

  /**
   * Tests visitors_update_8233().
   */
  public function testUpdate8233(): void {
    $schema = $this->createMock('Drupal\Core\Database\Schema');
    $this->database->expects($this->once())
      ->method('schema')
      ->willReturn($schema);

    $schema->expects($this->once())
      ->method('changeField')
      ->with(
        'visitors',
        'language',
        'language',
        [
          'type'     => 'varchar',
          'length'   => 3,
          'not null' => TRUE,
          'default'  => '',
        ]
      );

    visitors_update_8233();
  }

}
