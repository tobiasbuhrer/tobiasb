<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Unit\Cache;

use Drupal\Tests\UnitTestCase;
use Drupal\visitors\Cache\DateRangeCacheContext;
use Drupal\visitors\VisitorsDateRangeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Tests the DateRangeCacheContext class.
 */
#[CoversClass(DateRangeCacheContext::class)]
#[CoversMethod(DateRangeCacheContext::class, '__construct')]
#[CoversMethod(DateRangeCacheContext::class, 'getLabel')]
#[CoversMethod(DateRangeCacheContext::class, 'getContext')]
#[CoversMethod(DateRangeCacheContext::class, 'getCacheableMetadata')]
#[Group('visitors')]
class DateRangeCacheContextTest extends UnitTestCase {

  /**
   * The date range service.
   *
   * @var \Drupal\visitors\VisitorsDateRangeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $dateRange;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $time;

  /**
   * The date range cache context.
   *
   * @var \Drupal\visitors\Cache\DateRangeCacheContext
   */
  protected $context;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->dateRange = $this->createMock(VisitorsDateRangeInterface::class);
    $container->set('visitors.date_range', $this->dateRange);

    $this->time = $this->createMock(TimeInterface::class);
    $container->set('datetime.time', $this->time);

    \Drupal::setContainer($container);

    $this->context = new DateRangeCacheContext($this->dateRange, $this->time);
  }

  /**
   * Tests the constructor.
   */
  public function testConstructor() {
    $context = new DateRangeCacheContext($this->dateRange, $this->time);
    $this->assertInstanceOf(DateRangeCacheContext::class, $context);
  }

  /**
   * Tests the getLabel method.
   */
  public function testGetLabel() {
    $label = $this->context::getLabel();
    $this->assertEquals('Visitors Date Range Filter', $label);
  }

  /**
   * Tests the getContext method.
   */
  public function testGetContext() {
    $this->dateRange->expects($this->once())
      ->method('getStartTimestamp')
      ->willReturn(1234567890);

    $this->dateRange->expects($this->once())
      ->method('getEndTimestamp')
      ->willReturn(1234567891);

    $this->time->expects($this->once())
      ->method('getCurrentTime')
      ->willReturn(1234567892);

    $context = $this->context->getContext();
    $this->assertEquals('1234567890:1234567891', $context);
  }

  /**
   * Tests the getContext method.
   */
  public function testGetContextNow() {
    $this->dateRange->expects($this->once())
      ->method('getStartTimestamp')
      ->willReturn(1234567890);

    $this->dateRange->expects($this->once())
      ->method('getEndTimestamp')
      ->willReturn(2234567891);

    $this->time->expects($this->once())
      ->method('getCurrentTime')
      ->willReturn(1234567892);

    $context = $this->context->getContext();
    $this->assertEquals('1234567890:1234567892', $context);
  }

  /**
   * Tests the getCacheableMetadata method.
   */
  public function testGetCacheableMetadata() {
    $this->dateRange->expects($this->once())
      ->method('getEndTimestamp')
      ->willReturn(1234567891);

    $this->time->expects($this->once())
      ->method('getCurrentTime')
      ->willReturn(1234567892);

    $metadata = $this->context->getCacheableMetadata();
    $this->assertInstanceOf('Drupal\Core\Cache\CacheableMetadata', $metadata);

    $this->assertEquals(-1, $metadata->getCacheMaxAge());
  }

  /**
   * Tests the getCacheableMetadata method.
   */
  public function testGetCacheableMetadataFuture() {
    $this->dateRange->expects($this->once())
      ->method('getEndTimestamp')
      ->willReturn(2234567891);

    $this->time->expects($this->once())
      ->method('getCurrentTime')
      ->willReturn(1234567892);

    $metadata = $this->context->getCacheableMetadata();
    $this->assertInstanceOf('Drupal\Core\Cache\CacheableMetadata', $metadata);

    $this->assertEquals(0, $metadata->getCacheMaxAge());
  }

}
