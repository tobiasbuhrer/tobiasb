<?php

namespace Drupal\Tests\visitors_geoip\Unit\Service;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\visitors_geoip\GeoIpCity;
use Drupal\visitors_geoip\Service\GeoIpService;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\City;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Tests the GeoIpService.
 */
#[CoversClass(GeoIpService::class)]
#[CoversMethod(GeoIpService::class, '__construct')]
#[CoversMethod(GeoIpService::class, 'metadata')]
#[CoversMethod(GeoIpService::class, 'city')]
#[CoversMethod(GeoIpService::class, 'setReader')]
#[CoversMethod(GeoIpService::class, 'getReader')]
#[CoversMethod(GeoIpService::class, 'hasLibrary')]
#[CoversMethod(GeoIpService::class, 'hasExtension')]
#[Group('visitors_geoip')]
class GeoIpServiceTest extends UnitTestCase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The GeoIP reader service.
   *
   * @var \GeoIp2\Database\Reader|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $reader;

  /**
   * The GeoIpService instance being tested.
   *
   * @var \Drupal\visitors_geoip\Service\GeoIpService
   */
  protected $geoIpService;

  /**
   * The settings config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $settings;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->fileSystem = $this->createMock('\Drupal\Core\File\FileSystemInterface');
    $container->set('file_system', $this->fileSystem);

    $this->settings = $this->createMock('\Drupal\Core\Config\ImmutableConfig');
    $this->settings->expects($this->any())
      ->method('get')
      ->with('geoip_path')
      ->willReturn('test_path');
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $container->set('config.factory', $this->configFactory);
    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('visitors_geoip.settings')
      ->willReturn($this->settings);
    $this->reader = $this->createMock(Reader::class);

    \Drupal::setContainer($container);

    $this->geoIpService = new GeoIpService($this->configFactory, $this->fileSystem);
  }

  /**
   * Tests the constructor.
   */
  public function testConstructorBetterDatabase() {
    $this->fileSystem->expects($this->once())
      ->method('realPath')
      ->with('test_path/GeoIP2-City.mmdb')
      ->willReturn(TRUE);

    $this->expectException(\InvalidArgumentException::class);
    $geoIpService = new GeoIpService($this->configFactory, $this->fileSystem);
    $this->assertInstanceOf(GeoIpService::class, $geoIpService);
  }

  /**
   * Tests the constructor.
   */
  public function testConstructorFreeDatabase() {
    $this->fileSystem->expects($this->exactly(2))
      ->method('realPath')
      ->willReturnMap([
        ['test_path/GeoIP2-City.mmdb', FALSE],
        ['test_path/GeoLite2-City.mmdb', TRUE],
      ]);

    $this->expectException(\InvalidArgumentException::class);
    $geoIpService = new GeoIpService($this->configFactory, $this->fileSystem);
    $this->assertInstanceOf(GeoIpService::class, $geoIpService);
  }

  /**
   * Tests the metadata() method.
   */
  public function testMetadata() {
    // Mock the reader service.
    $metadata = $this->createMock('\MaxMind\Db\Reader\Metadata');
    $this->reader->expects($this->once())
      ->method('metadata')
      ->willReturn($metadata);
    $this->geoIpService->setReader($this->reader);
    // Call the metadata() method.
    $result = $this->geoIpService->metadata();

    // Assert the result.
    $this->assertEquals($metadata, $result);
  }

  /**
   * Tests the metadata() method with a NULL reader.
   */
  public function testMetadataNull() {
    $this->geoIpService->setReader(NULL);
    // Call the metadata() method.
    $result = $this->geoIpService->metadata();

    // Assert the result.
    $this->assertNull($result, 'Metadata should be NULL when there is no reader');
  }

  /**
   * Tests the city() method.
   */
  public function testCity() {
    $ipAddress = '127.0.0.1';

    $city = new City([
      'city' => ['names' => ['en' => 'Los Angeles']],
      'country' => ['iso_code' => 'US'],
      'subdivisions' => [['iso_code' => 'CA']],
      'continent' => ['code' => 'NA'],
      'location' => ['latitude' => 34.0522, 'longitude' => -118.2437],
    ]);
    $this->reader->expects($this->once())
      ->method('city')
      ->with($ipAddress)
      ->willReturn($city);
    $this->geoIpService->setReader($this->reader);
    $result = $this->geoIpService->city($ipAddress);

    $this->assertInstanceOf(GeoIpCity::class, $result);
    $this->assertSame('NA', $result->continentCode);
    $this->assertSame('US', $result->countryCode);
    $this->assertSame('CA', $result->regionCode);
    $this->assertSame('Los Angeles', $result->city);
    $this->assertSame(34.0522, $result->latitude);
    $this->assertSame(-118.2437, $result->longitude);
  }

  /**
   * Tests the city() method with a NULL reader.
   */
  public function testCityNull() {
    $ipAddress = '127.0.0.1';

    $this->geoIpService->setReader(NULL);
    // Call the city() method.
    $result = $this->geoIpService->city($ipAddress);

    // Assert the result.
    $this->assertNull($result, 'City should be NULL when there is no reader');
  }

  /**
   * A missing address returns NULL instead of throwing.
   */
  public function testCityReturnsNullWhenAddressIsNotInTheDatabase() {
    $ip_address = '10.0.0.1';
    $exception = new AddressNotFoundException(
      'The address 10.0.0.1 is not in the database.'
    );
    $this->reader->expects($this->once())
      ->method('city')
      ->with($ip_address)
      ->willThrowException($exception);
    $this->geoIpService->setReader($this->reader);

    $result = NULL;
    try {
      $result = $this->geoIpService->city($ip_address);
    }
    catch (\Throwable $thrown) {
      $this->fail($thrown->getMessage());
    }

    $this->assertNull($result);
  }

  /**
   * Tests the getReader() method.
   */
  public function testGetReader() {
    $this->geoIpService->setReader($this->reader);
    $this->assertSame($this->reader, $this->geoIpService->getReader());
  }

  /**
   * Tests the setReader() method.
   */
  public function testSetReader() {
    $this->geoIpService->setReader($this->reader);
    $this->assertSame($this->reader, $this->geoIpService->getReader());
  }

  /**
   * Tests the hasLibrary method.
   */
  public function testHasLibrary():void {
    $this->assertTrue($this->geoIpService->hasLibrary());
  }

  /**
   * Tests the hasLibrary method with a missing class name.
   */
  public function testHasLibraryWithClassName():void {
    $this->assertFalse($this->geoIpService->hasLibrary('Missing\Class'));
  }

  /**
   * Tests the hasExtension method.
   *
   * We can not guarantee that the maxminddb extension is installed on the
   * testing environment, so we will test the method with a fake extension.
   */
  public function testHasExtension(): void {
    $this->assertFalse($this->geoIpService->hasExtension('fake_extension'));
  }

}
