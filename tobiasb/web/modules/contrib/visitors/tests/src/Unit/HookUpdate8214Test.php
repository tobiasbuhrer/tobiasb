<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\Group;

require_once __DIR__ . '/../../../visitors.install';

/**
 * Tests visitors_update_8214().
 */
#[Group('visitors')]
final class HookUpdate8214Test extends UnitTestCase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $database;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $this->database = $this->createMock('Drupal\Core\Database\Connection');
    $container->set('database', $this->database);

    $this->state = $this->createMock('Drupal\Core\State\StateInterface');
    $container->set('state', $this->state);

    \Drupal::setContainer($container);
  }

  /**
   * The visitors_ip field spec used by the update.
   *
   * @return array<string, mixed>
   *   The field spec.
   */
  private function spec(): array {
    return [
      'type'     => 'varchar',
      'length'   => 45,
      'not null' => TRUE,
      'default'  => '',
    ];
  }

  /**
   * Tests visitors_update_8214 when the field exists (changeField path).
   */
  public function testUpdate8214FieldExists(): void {
    $schema = $this->createMock('Drupal\Core\Database\Schema');
    $this->database->expects($this->once())
      ->method('schema')
      ->willReturn($schema);
    $schema->expects($this->once())
      ->method('fieldExists')
      ->with('visitors', 'visitors_ip')
      ->willReturn(TRUE);
    $schema->expects($this->once())
      ->method('changeField')
      ->with('visitors', 'visitors_ip', 'visitors_ip', $this->spec())
      ->willReturn(TRUE);
    $schema->expects($this->never())
      ->method('addField');

    $this->state->expects($this->once())
      ->method('set')
      ->with('visitors.rebuild.ip_address', TRUE);

    visitors_update_8214();
  }

  /**
   * Tests visitors_update_8214 when the field is missing (addField path).
   */
  public function testUpdate8214FieldMissing(): void {
    $schema = $this->createMock('Drupal\Core\Database\Schema');
    $this->database->expects($this->once())
      ->method('schema')
      ->willReturn($schema);
    $schema->expects($this->once())
      ->method('fieldExists')
      ->with('visitors', 'visitors_ip')
      ->willReturn(FALSE);
    $schema->expects($this->never())
      ->method('changeField');
    $schema->expects($this->once())
      ->method('addField')
      ->with('visitors', 'visitors_ip', $this->spec());

    $this->state->expects($this->once())
      ->method('set')
      ->with('visitors.rebuild.ip_address', TRUE);

    visitors_update_8214();
  }

}
