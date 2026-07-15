<?php

namespace Drupal\Tests\visitors\Unit\Controller\Report;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\visitors\Controller\Report\HitDetails;
use Drupal\visitors\VisitorsReportInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for the HitDetails controller.
 *
 * @group visitors
 * @uses \Drupal\visitors\Controller\Report\HitDetails
 * @coversDefaultClass \Drupal\visitors\Controller\Report\HitDetails
 */
class HitDetailsControllerTest extends UnitTestCase {

  /**
   * The mocked Date Formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $dateFormatter;

  /**
   * The mocked Visitors Report service.
   *
   * @var \Drupal\visitors\VisitorsReportInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $visitorsReport;

  /**
   * The HitDetails controller under test.
   *
   * @var \Drupal\visitors\Controller\Report\HitDetails
   */
  protected $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $this->dateFormatter = $this->createMock(DateFormatterInterface::class);
    $container->set('date.formatter', $this->dateFormatter);

    $this->visitorsReport = $this->createMock(VisitorsReportInterface::class);
    $container->set('visitors.report', $this->visitorsReport);

    \Drupal::setContainer($container);

    $this->controller = HitDetails::create($container);
  }

  /**
   * Tests the create() method.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $container = \Drupal::getContainer();
    $controller = HitDetails::create($container);
    $this->assertInstanceOf(HitDetails::class, $controller);
  }

  /**
   * Tests the constructor.
   *
   * @covers ::__construct
   */
  public function testConstruct(): void {
    $controller = new HitDetails($this->dateFormatter, $this->visitorsReport);
    $this->assertInstanceOf(HitDetails::class, $controller);
  }

  /**
   * Tests the display() method.
   *
   * @covers ::display
   */
  public function testDisplay(): void {
    $hitId = 123;
    $rows  = [];

    $this->visitorsReport->expects($this->once())
      ->method('hitDetails')
      ->with($hitId)
      ->willReturn($rows);

    $expectedOutput = [
      'visitors_table' => [
        '#type'  => 'table',
        '#rows'  => $rows,
      ],
    ];

    $output = $this->controller->display($hitId);
    $this->assertEquals($expectedOutput, $output);
  }

  /**
   * Tests display() passes a large ID to the report service unchanged.
   *
   * IDs >= 1,000 were previously delivered to the controller as comma-
   * formatted strings (e.g. "2,011") from Views token substitution, causing
   * the route to cast the value to "2" and return an empty page. The fix
   * lives in the view config (separator: ''), but the controller must also
   * forward the raw integer correctly.
   *
   * @covers ::display
   */
  public function testDisplayWithLargeHitId(): void {
    $hitId = 2011;
    $rows  = [
      ['Field', 'Value'],
    ];

    $this->visitorsReport->expects($this->once())
      ->method('hitDetails')
      ->with($hitId)
      ->willReturn($rows);

    $output = $this->controller->display($hitId);

    $this->assertSame(
      $rows,
      $output['visitors_table']['#rows'],
      'The report service must receive the full, unformatted hit ID.'
    );
  }

}
