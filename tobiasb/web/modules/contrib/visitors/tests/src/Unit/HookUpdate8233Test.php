<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\Group;

require_once __DIR__ . '/../../../visitors.install';

/**
 * Tests visitors_update_8233().
 */
#[Group('visitors')]
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
   * The language field spec used by the update.
   *
   * @return array<string, mixed>
   *   The field spec.
   */
  private function spec(): array {
    return [
      'type'     => 'varchar',
      'length'   => 3,
      'not null' => TRUE,
      'default'  => '',
    ];
  }

  /**
   * Tests visitors_update_8233() when the language field exists (changeField).
   */
  public function testUpdate8233FieldExists(): void {
    $schema = $this->createMock('Drupal\Core\Database\Schema');
    $this->database->expects($this->once())
      ->method('schema')
      ->willReturn($schema);

    $schema->expects($this->once())
      ->method('fieldExists')
      ->with('visitors', 'language')
      ->willReturn(TRUE);
    $schema->expects($this->once())
      ->method('changeField')
      ->with(
        'visitors',
        'language',
        'language',
        $this->spec()
      );
    $schema->expects($this->never())
      ->method('addField');

    visitors_update_8233();
  }

  /**
   * Tests visitors_update_8233() when the language field is missing (addField).
   */
  public function testUpdate8233FieldMissing(): void {
    $schema = $this->createMock('Drupal\Core\Database\Schema');
    $this->database->expects($this->once())
      ->method('schema')
      ->willReturn($schema);

    $schema->expects($this->once())
      ->method('fieldExists')
      ->with('visitors', 'language')
      ->willReturn(FALSE);
    $schema->expects($this->never())
      ->method('changeField');
    $schema->expects($this->once())
      ->method('addField')
      ->with('visitors', 'language', $this->spec());

    visitors_update_8233();
  }

}
