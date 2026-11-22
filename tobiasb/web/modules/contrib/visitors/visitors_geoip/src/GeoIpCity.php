<?php

declare(strict_types=1);

namespace Drupal\visitors_geoip;

/**
 * Location fields shared by geoip2 2.x and 3.x city responses.
 */
final class GeoIpCity {

  /**
   * Constructs a normalized city location.
   *
   * @param string|null $continentCode
   *   The continent code.
   * @param string|null $countryCode
   *   The country ISO code.
   * @param string|null $regionCode
   *   The first subdivision ISO code.
   * @param string|null $city
   *   The English city name.
   * @param float|null $latitude
   *   The latitude.
   * @param float|null $longitude
   *   The longitude.
   */
  public function __construct(
    public readonly ?string $continentCode,
    public readonly ?string $countryCode,
    public readonly ?string $regionCode,
    public readonly ?string $city,
    public readonly ?float $latitude,
    public readonly ?float $longitude,
  ) {}

  /**
   * Builds a location from a geoip2 2.x or 3.x city model.
   *
   * Version 3 exposes the localized name as city->name. Version 2 stores
   * the same value in city->names['en'] when the locale list defaults to
   * English. A non-empty name wins when both are present.
   *
   * @param object $location
   *   The library city response.
   *
   * @return self
   *   The normalized location.
   */
  public static function fromLibrary(object $location): self {
    $city = self::objectProperty($location, 'city');
    $city_name = NULL;
    if ($city !== NULL) {
      $name = self::objectProperty($city, 'name');
      if (is_string($name) && $name !== '') {
        $city_name = $name;
      }
      else {
        $names = self::objectProperty($city, 'names');
        $english = is_array($names) ? ($names['en'] ?? NULL) : NULL;
        if (is_string($english)) {
          $city_name = $english;
        }
      }
    }

    $region_code = NULL;
    $subdivisions = self::objectProperty($location, 'subdivisions');
    $first = is_array($subdivisions) ? ($subdivisions[0] ?? NULL) : NULL;
    if (is_object($first)) {
      $region = self::objectProperty($first, 'isoCode');
      $region_code = is_string($region) ? $region : NULL;
    }

    $continent = self::objectProperty($location, 'continent');
    $country = self::objectProperty($location, 'country');
    $coordinates = self::objectProperty($location, 'location');

    $continent_code = NULL;
    if ($continent !== NULL) {
      $code = self::objectProperty($continent, 'code');
      $continent_code = is_string($code) ? $code : NULL;
    }

    $country_code = NULL;
    if ($country !== NULL) {
      $code = self::objectProperty($country, 'isoCode');
      $country_code = is_string($code) ? $code : NULL;
    }

    $latitude = NULL;
    $longitude = NULL;
    if ($coordinates !== NULL) {
      $lat = self::objectProperty($coordinates, 'latitude');
      $lon = self::objectProperty($coordinates, 'longitude');
      $latitude = is_numeric($lat) ? (float) $lat : NULL;
      $longitude = is_numeric($lon) ? (float) $lon : NULL;
    }

    return new self(
      $continent_code,
      $country_code,
      $region_code,
      $city_name,
      $latitude,
      $longitude,
    );
  }

  /**
   * Reads a public property without warning when it is absent.
   *
   * @param object $object
   *   The object to read.
   * @param string $property
   *   The property name.
   *
   * @return mixed
   *   The property value, or NULL when it is not set.
   */
  private static function objectProperty(object $object, string $property): mixed {
    if (!property_exists($object, $property) && !isset($object->{$property})) {
      return NULL;
    }
    return $object->{$property} ?? NULL;
  }

}
