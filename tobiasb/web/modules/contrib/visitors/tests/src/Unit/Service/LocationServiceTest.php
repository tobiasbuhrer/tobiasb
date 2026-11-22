<?php

namespace Drupal\Tests\visitors\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\visitors\Service\LocationService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Tests the CookieService class.
 */
#[CoversClass(LocationService::class)]
#[CoversMethod(LocationService::class, '__construct')]
#[CoversMethod(LocationService::class, 'getCountryLabel')]
#[CoversMethod(LocationService::class, 'getContinent')]
#[CoversMethod(LocationService::class, 'getContinentLabel')]
#[CoversMethod(LocationService::class, 'isValidCountryCode')]
#[UsesClass(LocationService::class)]
#[Group('visitors')]
class LocationServiceTest extends UnitTestCase {

  /**
   * The location service.
   *
   * @var \Drupal\visitors\Service\LocationService
   */
  protected $location;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();

    \Drupal::setContainer($container);

    $this->location = new LocationService($string_translation);
  }

  /**
   * Tests the construct method.
   */
  public function testConstruct() {
    $counter = new LocationService($this->getStringTranslationStub());
    $this->assertInstanceOf(LocationService::class, $counter);
  }

  /**
   * Tests the getCountryLabel method.
   */
  public function testGetCountryLabel() {
    $this->assertSame('United States', (string) $this->location->getCountryLabel('US'));
    $this->assertSame('United States', (string) $this->location->getCountryLabel('us'));
    $this->assertSame('United Kingdom', (string) $this->location->getCountryLabel('GB'));
    $this->assertSame('Unknown', (string) $this->location->getCountryLabel('ZZ'));
  }

  /**
   * Tests the getContinent method.
   */
  public function testGetContinent() {
    $this->assertSame('NA', $this->location->getContinent('US'));
    $this->assertSame('NA', $this->location->getContinent('us'));
    $this->assertSame('EU', $this->location->getContinent('GB'));
    $this->assertSame('', $this->location->getContinent('ZZ'));
  }

  /**
   * Tests the getContinentLabel method.
   */
  public function testGetContinentLabel() {
    $this->assertSame('North America', (string) $this->location->getContinentLabel('NA'));
    $this->assertSame('Europe', (string) $this->location->getContinentLabel('EU'));
    $this->assertSame('Unknown', (string) $this->location->getContinentLabel('ZZ'));
  }

  /**
   * Tests the isValidCountryCode method.
   */
  public function testIsValidCountryCode() {
    $this->assertTrue($this->location->isValidCountryCode('US'));
    $this->assertTrue($this->location->isValidCountryCode('us'));
    $this->assertTrue($this->location->isValidCountryCode('GB'));
    $this->assertFalse($this->location->isValidCountryCode('ZZ'));
    $this->assertFalse($this->location->isValidCountryCode('419'));
  }

}
