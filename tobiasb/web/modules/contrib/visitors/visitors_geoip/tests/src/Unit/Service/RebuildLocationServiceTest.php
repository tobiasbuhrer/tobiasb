<?php

namespace Drupal\Tests\visitors_geoip\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\visitors_geoip\GeoIpCity;
use Drupal\visitors_geoip\Service\RebuildLocationService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Tests the RebuildLocationService.
 */
#[CoversClass(RebuildLocationService::class)]
#[CoversMethod(RebuildLocationService::class, '__construct')]
#[CoversMethod(RebuildLocationService::class, 'getLocations')]
#[CoversMethod(RebuildLocationService::class, 'rebuild')]
#[Group('visitors_geoip')]
class RebuildLocationServiceTest extends UnitTestCase {

  /**
   * The rebuild location service.
   *
   * @var \Drupal\visitors_geoip\Service\RebuildLocationService
   */
  protected $service;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $database;

  /**
   * The GeoIP service.
   *
   * @var \Drupal\visitors_geoip\VisitorsGeoIpInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $geoipService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->database = $this->createMock('Drupal\Core\Database\Connection');
    $container->set('database', $this->database);

    $this->geoipService = $this->createMock('Drupal\visitors_geoip\VisitorsGeoIpInterface');
    $container->set('visitors_geoip.rebuild.location', $this->geoipService);

    $this->service = new RebuildLocationService($this->database, $this->geoipService);
  }

  /**
   * Tests the constructor.
   */
  public function testConstruct(): void {
    $service = new RebuildLocationService($this->database, $this->geoipService);

    $this->assertInstanceOf(RebuildLocationService::class, $service);
  }

  /**
   * Tests getLocations() method.
   */
  public function testGetLocations(): void {
    $statement = $this->createMock('Drupal\Core\Database\Statement\StatementBase');
    $select = $this->createMock('Drupal\Core\Database\Query\SelectInterface');
    $this->database->expects($this->once())
      ->method('select')
      ->with('visitors', 'v')
      ->willReturn($select);

    $select->expects($this->once())
      ->method('fields')
      ->with('v', ['visitors_ip'])
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('location_latitude', NULL, 'IS NULL')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('distinct')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);
    $statement->expects($this->once())
      ->method('fetchAll')
      ->willReturn(['127.0.0.1']);

    $result = $this->service->getLocations();
  }

  /**
   * Tests the rebuild() method.
   */
  public function testRebuildNone(): void {
    $ip_address = '127.0.0.1';
    $this->geoipService->expects($this->once())
      ->method('city')
      ->with($ip_address)
      ->willReturn(NULL);

    $this->service->rebuild($ip_address);
  }

  /**
   * Tests the rebuild() method.
   */
  public function testRebuild(): void {

    $city = new GeoIpCity(
      'NA',
      'US',
      'CA',
      'Los Angeles',
      34.0522,
      -118.2437,
    );

    $ip_address = '127.0.0.1';
    $this->geoipService->expects($this->once())
      ->method('city')
      ->with($ip_address)
      ->willReturn($city);

    $update = $this->createMock('Drupal\Core\Database\Query\Update');
    $this->database->expects($this->once())
      ->method('update')
      ->with('visitors')
      ->willReturn($update);
    $update->expects($this->once())
      ->method('fields')
      ->with([
        'location_continent' => 'NA',
        'location_country' => 'US',
        'location_region' => 'CA',
        'location_city' => 'Los Angeles',
        'location_latitude' => 34.0522,
        'location_longitude' => -118.2437,
      ])
      ->willReturnSelf();
    $update->expects($this->exactly(2))
      ->method('condition')
      ->willReturnSelf();
    $update->expects($this->once())
      ->method('execute')
      ->willReturn(1);

    $this->service->rebuild($ip_address);
  }

}
