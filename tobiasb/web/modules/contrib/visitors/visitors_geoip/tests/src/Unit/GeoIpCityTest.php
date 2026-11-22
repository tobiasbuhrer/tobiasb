<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors_geoip\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\visitors_geoip\GeoIpCity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Tests normalized GeoIP city responses.
 */
#[CoversClass(GeoIpCity::class)]
#[CoversMethod(GeoIpCity::class, 'fromLibrary')]
#[CoversMethod(GeoIpCity::class, '__construct')]
#[Group('visitors_geoip')]
class GeoIpCityTest extends UnitTestCase {

  /**
   * A version 3 response uses the localized city name.
   */
  public function testVersion3CityName(): void {
    $record = $this->locationRecord('Chicago', NULL);

    $city = GeoIpCity::fromLibrary($record);

    $this->assertSame('Chicago', $city->city);
  }

  /**
   * A version 2 response uses the English names entry.
   */
  public function testVersion2CityName(): void {
    $record = $this->locationRecord(NULL, ['en' => 'Chicago']);

    $city = GeoIpCity::fromLibrary($record);

    $this->assertSame('Chicago', $city->city);
  }

  /**
   * The localized name wins when both shapes are present.
   */
  public function testNameWinsOverNames(): void {
    $record = $this->locationRecord('Chicago', ['en' => 'Los Angeles']);

    $city = GeoIpCity::fromLibrary($record);

    $this->assertSame('Chicago', $city->city);
  }

  /**
   * An empty subdivision list leaves the region unset.
   */
  public function testEmptySubdivisions(): void {
    $record = $this->locationRecord('Chicago', NULL);
    $record->subdivisions = [];

    $city = GeoIpCity::fromLibrary($record);

    $this->assertNull($city->regionCode);
  }

  /**
   * Missing coordinates stay null.
   */
  public function testMissingCoordinates(): void {
    $record = $this->locationRecord('Chicago', NULL);
    $record->location = new \stdClass();

    $city = GeoIpCity::fromLibrary($record);

    $this->assertNull($city->latitude);
    $this->assertNull($city->longitude);
  }

  /**
   * A full record maps every stored location field.
   */
  public function testFullRecord(): void {
    $record = $this->locationRecord('Chicago', ['en' => 'Chicago']);

    $city = GeoIpCity::fromLibrary($record);

    $this->assertSame('NA', $city->continentCode);
    $this->assertSame('US', $city->countryCode);
    $this->assertSame('CA', $city->regionCode);
    $this->assertSame('Chicago', $city->city);
    $this->assertSame(37.751, $city->latitude);
    $this->assertSame(-97.822, $city->longitude);
  }

  /**
   * Builds a stand-in for a geoip2 city model.
   *
   * @param string|null $name
   *   The localized city name.
   * @param array<string, string>|null $names
   *   The locale map, when the stand-in includes one.
   *
   * @return \stdClass
   *   The library-shaped record.
   */
  private function locationRecord(?string $name, ?array $names): \stdClass {
    $continent = new \stdClass();
    $continent->code = 'NA';
    $country = new \stdClass();
    $country->isoCode = 'US';
    $coordinates = new \stdClass();
    $coordinates->latitude = 37.751;
    $coordinates->longitude = -97.822;
    $subdivision = new \stdClass();
    $subdivision->isoCode = 'CA';
    $city = new \stdClass();
    $city->name = $name;
    if ($names !== NULL) {
      $city->names = $names;
    }

    $record = new \stdClass();
    $record->continent = $continent;
    $record->country = $country;
    $record->subdivisions = [$subdivision];
    $record->city = $city;
    $record->location = $coordinates;

    return $record;
  }

}
