<?php

namespace Drupal\Tests\visitors\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\visitors\Service\OnlineService;
use Drupal\visitors\VisitorsOnlineInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Tests the OnlineService class.
 */
#[CoversClass(OnlineService::class)]
#[CoversMethod(OnlineService::class, '__construct')]
#[CoversMethod(OnlineService::class, 'getLast30Minutes')]
#[CoversMethod(OnlineService::class, 'query')]
#[CoversMethod(OnlineService::class, 'getLast24Hours')]
#[CoversMethod(OnlineService::class, 'getYesterday30Minutes')]
#[CoversMethod(OnlineService::class, 'getYesterday24Hours')]
#[CoversMethod(OnlineService::class, 'getLastWeek30Minutes')]
#[CoversMethod(OnlineService::class, 'getLastWeek24Hours')]
#[Group('visitors')]
class OnlineServiceTest extends UnitTestCase {

  /**
   * The current time.
   */
  const NOW = 1741560323;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $database;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $time;

  /**
   * The online service.
   *
   * @var \Drupal\visitors\Service\OnlineService
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $this->database = $this->createMock('\Drupal\Core\Database\Connection');
    $container->set('database', $this->database);

    $this->time = $this->createMock('\Drupal\Component\Datetime\TimeInterface');
    $container->set('datetime.time', $this->time);

    \Drupal::setContainer($container);

    $this->service = new OnlineService($this->database, $this->time);
  }

  /**
   * Tests the constructor.
   */
  public function testConstructor() {
    $service = new OnlineService($this->database, $this->time);
    $this->assertInstanceOf(OnlineService::class, $service);
  }

  /**
   * Tests the getLast30Minutes method.
   */
  public function testGetLast30Minutes(): void {
    $end = self::NOW;
    $start = $end - VisitorsOnlineInterface::MINUTE_30;
    $this->time->expects($this->once())
      ->method('getRequestTime')
      ->willReturn(self::NOW);

    $statement = $this->createMock('\Drupal\Core\Database\StatementInterface');
    $statement->expects($this->once())
      ->method('fetchField')
      ->willReturn(1);
    $select = $this->createMock('\Drupal\Core\Database\Query\SelectInterface');
    $select->expects($this->once())
      ->method('addExpression')
      ->with('COUNT(DISTINCT v.visitor_id)', 'count')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('v.visitors_date_time', [$start, $end], 'BETWEEN')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('visitors', 'v')
      ->willReturn($select);

    $count = $this->service->getLast30Minutes();

    $this->assertEquals(1, $count);
    $this->assertEquals(VisitorsOnlineInterface::MINUTE_30, ($end - $start));
  }

  /**
   * Tests the getLast24Hours method.
   */
  public function testGetLast24Hours(): void {
    $end = self::NOW;
    $start = $end - VisitorsOnlineInterface::HOUR_24;
    $this->time->expects($this->once())
      ->method('getRequestTime')
      ->willReturn(self::NOW);

    $statement = $this->createMock('\Drupal\Core\Database\StatementInterface');
    $statement->expects($this->once())
      ->method('fetchField')
      ->willReturn(1);
    $select = $this->createMock('\Drupal\Core\Database\Query\SelectInterface');
    $select->expects($this->once())
      ->method('addExpression')
      ->with('COUNT(DISTINCT v.visitor_id)', 'count')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('v.visitors_date_time', [$start, $end], 'BETWEEN')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('visitors', 'v')
      ->willReturn($select);

    $count = $this->service->getLast24Hours();

    $this->assertEquals(1, $count);
    $this->assertEquals(VisitorsOnlineInterface::HOUR_24, ($end - $start));
  }

  /**
   * Tests the getYesterday30Minutes method.
   */
  public function testGetYesterday30Minutes(): void {
    $end = self::NOW - VisitorsOnlineInterface::HOUR_24;
    $start = $end - VisitorsOnlineInterface::MINUTE_30;
    $this->time->expects($this->once())
      ->method('getRequestTime')
      ->willReturn(self::NOW);

    $statement = $this->createMock('\Drupal\Core\Database\StatementInterface');
    $statement->expects($this->once())
      ->method('fetchField')
      ->willReturn(1);
    $select = $this->createMock('\Drupal\Core\Database\Query\SelectInterface');
    $select->expects($this->once())
      ->method('addExpression')
      ->with('COUNT(DISTINCT v.visitor_id)', 'count')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('v.visitors_date_time', [$start, $end], 'BETWEEN')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('visitors', 'v')
      ->willReturn($select);

    $count = $this->service->getYesterday30Minutes();

    $this->assertEquals(1, $count);
    $this->assertEquals(VisitorsOnlineInterface::MINUTE_30, ($end - $start));
  }

  /**
   * Tests the getYesterday24Hours method.
   */
  public function testGetYesterday24Hours(): void {
    $end = self::NOW - VisitorsOnlineInterface::HOUR_24;
    $start = $end - VisitorsOnlineInterface::HOUR_24;
    $this->time->expects($this->once())
      ->method('getRequestTime')
      ->willReturn(self::NOW);

    $statement = $this->createMock('\Drupal\Core\Database\StatementInterface');
    $statement->expects($this->once())
      ->method('fetchField')
      ->willReturn(1);
    $select = $this->createMock('\Drupal\Core\Database\Query\SelectInterface');
    $select->expects($this->once())
      ->method('addExpression')
      ->with('COUNT(DISTINCT v.visitor_id)', 'count')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('v.visitors_date_time', [$start, $end], 'BETWEEN')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('visitors', 'v')
      ->willReturn($select);

    $count = $this->service->getYesterday24Hours();

    $this->assertEquals(1, $count);
    $this->assertEquals(VisitorsOnlineInterface::HOUR_24, ($end - $start));
  }

  /**
   * Tests the getLastWeek30Minutes method.
   */
  public function testGetLastWeek30Minutes(): void {
    $end = self::NOW - VisitorsOnlineInterface::DAY_7;
    $start = $end - VisitorsOnlineInterface::MINUTE_30;
    $this->time->expects($this->once())
      ->method('getRequestTime')
      ->willReturn(self::NOW);

    $statement = $this->createMock('\Drupal\Core\Database\StatementInterface');
    $statement->expects($this->once())
      ->method('fetchField')
      ->willReturn(1);
    $select = $this->createMock('\Drupal\Core\Database\Query\SelectInterface');
    $select->expects($this->once())
      ->method('addExpression')
      ->with('COUNT(DISTINCT v.visitor_id)', 'count')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('v.visitors_date_time', [$start, $end], 'BETWEEN')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('visitors', 'v')
      ->willReturn($select);

    $count = $this->service->getLastWeek30Minutes();

    $this->assertEquals(1, $count);
    $this->assertEquals(VisitorsOnlineInterface::MINUTE_30, ($end - $start));
  }

  /**
   * Tests the getLastWeek24Hours method.
   */
  public function testGetLastWeek24Hours(): void {
    $end = self::NOW - VisitorsOnlineInterface::DAY_7;
    $start = $end - VisitorsOnlineInterface::HOUR_24;
    $this->time->expects($this->once())
      ->method('getRequestTime')
      ->willReturn(self::NOW);

    $statement = $this->createMock('\Drupal\Core\Database\StatementInterface');
    $statement->expects($this->once())
      ->method('fetchField')
      ->willReturn(1);
    $select = $this->createMock('\Drupal\Core\Database\Query\SelectInterface');
    $select->expects($this->once())
      ->method('addExpression')
      ->with('COUNT(DISTINCT v.visitor_id)', 'count')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('v.visitors_date_time', [$start, $end], 'BETWEEN')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('visitors', 'v')
      ->willReturn($select);

    $count = $this->service->getLastWeek24Hours();

    $this->assertEquals(1, $count);
    $this->assertEquals(VisitorsOnlineInterface::HOUR_24, ($end - $start));
  }

}
