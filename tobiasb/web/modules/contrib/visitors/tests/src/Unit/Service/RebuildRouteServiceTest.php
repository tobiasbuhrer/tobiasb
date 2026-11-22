<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Unit\Service;

use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Tests\UnitTestCase;
use Drupal\visitors\Service\RebuildRouteService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Tests the RebuildRouteService class.
 */
#[CoversClass(RebuildRouteService::class)]
#[CoversMethod(RebuildRouteService::class, '__construct')]
#[CoversMethod(RebuildRouteService::class, 'getPaths')]
#[CoversMethod(RebuildRouteService::class, 'rebuild')]
#[Group('visitors')]
class RebuildRouteServiceTest extends UnitTestCase {

  /**
   * The report service.
   *
   * @var \Drupal\visitors\Service\RebuildRouteService
   */
  protected $service;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $database;

  /**
   * The route matcher.
   *
   * @var \Symfony\Component\Routing\Matcher\RequestMatcherInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $routeMatcher;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->database = $this->createMock('\Drupal\Core\Database\Connection');
    $container->set('database', $this->database);

    $this->routeMatcher = $this->createMock('\Symfony\Component\Routing\Matcher\RequestMatcherInterface');
    $container->set('router.matcher', $this->routeMatcher);

    $this->logger = $this->createMock('\Psr\Log\LoggerInterface');
    $container->set('logger.factory', $this->logger);

    \Drupal::setContainer($container);

    $this->service = new RebuildRouteService(
      $this->database,
      $this->routeMatcher,
      $this->logger,
    );
  }

  /**
   * Tests the constructor.
   */
  public function testConstructor(): void {
    $service = new RebuildRouteService(
      $this->database,
      $this->routeMatcher,
      $this->logger,
    );
    $this->assertInstanceOf(RebuildRouteService::class, $service);
  }

  /**
   * Tests the getPaths method.
   */
  public function testGetPaths(): void {
    $statement = $this->createMock('\Drupal\Core\Database\StatementInterface');
    $statement->expects($this->once())
      ->method('fetchAll')
      ->willReturn(['node/1']);
    $select = $this->createMock('\Drupal\Core\Database\Query\SelectInterface');
    $this->database->expects($this->once())
      ->method('select')
      ->with('visitors', 'v')
      ->willReturn($select);
    $select->expects($this->once())
      ->method('fields')
      ->with('v', ['visitors_path'])
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('route', '')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('distinct')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $paths = $this->service->getPaths();
    $this->assertEquals(['node/1'], $paths);
  }

  /**
   * Tests the getPaths method.
   */
  public function testGetPathsException(): void {

    $select = $this->createMock('\Drupal\Core\Database\Query\SelectInterface');
    $this->database->expects($this->once())
      ->method('select')
      ->with('visitors', 'v')
      ->willReturn($select);
    $select->expects($this->once())
      ->method('fields')
      ->with('v', ['visitors_path'])
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('route', '')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('distinct')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willThrowException(new \Exception());

    $paths = $this->service->getPaths();
    $this->assertEquals([], $paths);
  }

  /**
   * Tests the rebuild method.
   */
  public function testRebuildNoRoute(): void {
    $this->routeMatcher->expects($this->once())
      ->method('matchRequest')
      ->willReturn([]);

    $count = $this->service->rebuild('unknown');
    $this->assertEquals(0, $count);
  }

  /**
   * Tests the rebuild method.
   */
  public function testRebuildHasRoute(): void {
    $this->routeMatcher->expects($this->once())
      ->method('matchRequest')
      ->willReturn([
        '_route' => 'entity.node.canonical',
      ]);

    $update = $this->createMock('\Drupal\Core\Database\Query\Update');
    $update->expects($this->once())
      ->method('fields')
      ->with(['route' => 'entity.node.canonical'])
      ->willReturnSelf();
    $update->expects($this->once())
      ->method('condition')
      ->with('visitors_path', 'node/1')
      ->willReturnSelf();
    $update->expects($this->once())
      ->method('execute')
      ->willReturn(10);
    $this->database->expects($this->once())
      ->method('update')
      ->with('visitors')
      ->willReturn($update);

    $count = $this->service->rebuild('node/1');
    $this->assertEquals(10, $count);
  }

  /**
   * Tests the rebuild method.
   */
  public function testRebuildHasRouteUpdateException(): void {
    $this->routeMatcher->expects($this->once())
      ->method('matchRequest')
      ->willReturn([
        '_route' => 'entity.node.canonical',
      ]);

    $update = $this->createMock('\Drupal\Core\Database\Query\Update');
    $update->expects($this->once())
      ->method('fields')
      ->with(['route' => 'entity.node.canonical'])
      ->willReturnSelf();
    $update->expects($this->once())
      ->method('condition')
      ->with('visitors_path', 'node/1')
      ->willReturnSelf();
    $update->expects($this->once())
      ->method('execute')
      ->willThrowException(new \Exception());
    $this->database->expects($this->once())
      ->method('update')
      ->with('visitors')
      ->willReturn($update);

    $count = $this->service->rebuild('node/1');
    $this->assertEquals(0, $count);
  }

  /**
   * Tests the rebuild method.
   */
  public function testRebuildResourceNotFoundException(): void {
    $this->routeMatcher->expects($this->once())
      ->method('matchRequest')
      ->willThrowException(new ResourceNotFoundException());

    $count = $this->service->rebuild('unknown');
    $this->assertEquals(0, $count);
  }

  /**
   * Tests the rebuild method.
   */
  public function testRebuildException(): void {
    $this->routeMatcher->expects($this->once())
      ->method('matchRequest')
      ->willThrowException(new \Exception());

    $count = $this->service->rebuild('unknown');
    $this->assertEquals(0, $count);
  }

  /**
   * Tests the rebuild method.
   */
  public function testRebuildParamNotConvertedException(): void {
    $this->routeMatcher->expects($this->once())
      ->method('matchRequest')
      ->willThrowException(new ParamNotConvertedException(
        "An error occurred",
        0,
        NULL,
        "entity.node.canonical",
        [],
      ));
    $update = $this->createMock('\Drupal\Core\Database\Query\Update');
    $update->expects($this->once())
      ->method('fields')
      ->with(['route' => 'entity.node.canonical'])
      ->willReturnSelf();
    $update->expects($this->once())
      ->method('condition')
      ->with('visitors_path', 'node/1')
      ->willReturnSelf();
    $update->expects($this->once())
      ->method('execute')
      ->willReturn(7);
    $this->database->expects($this->once())
      ->method('update')
      ->with('visitors')
      ->willReturn($update);

    $count = $this->service->rebuild('node/1');
    $this->assertEquals(7, $count);
  }

}
