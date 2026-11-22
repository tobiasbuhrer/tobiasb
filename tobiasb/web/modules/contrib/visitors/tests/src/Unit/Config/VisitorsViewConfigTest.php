<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Unit\Config;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;
use PHPUnit\Framework\Attributes\Group;

/**
 * Validates the visitors view config prevents comma-encoded hit-details URLs.
 *
 * The numeric field handler for visitors_id applies a thousands separator by
 * default. Views substitutes the rendered value into link tokens, so an ID
 * such as 2011 becomes "2,011" and is URL-encoded to "2%2C011", breaking
 * navigation to /visitors/hits/{hit_id}. This test guards against the
 * separator being re-introduced in the shipped optional config.
 */
#[Group('visitors')]
class VisitorsViewConfigTest extends UnitTestCase {

  /**
   * Absolute path to the module root (four directories above this file).
   */
  private string $moduleRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // __DIR__ = tests/src/Unit/Config; four levels up is the module root.
    $this->moduleRoot = realpath(__DIR__ . '/../../../../');
  }

  /**
   * Returns the separator value from a specific display field in a view YAML.
   *
   * @param string $yaml_path
   *   Absolute path to the view YAML file.
   * @param string $display_id
   *   The display identifier to inspect.
   * @param string $field_id
   *   The field identifier to inspect within the display.
   *
   * @return string|null
   *   The separator value, or NULL if not found.
   */
  private function getSeparator(
    string $yaml_path,
    string $display_id,
    string $field_id,
  ): ?string {
    $config = Yaml::parseFile($yaml_path);

    return $config['display'][$display_id]['display_options']['fields'][$field_id]['separator']
      ?? NULL;
  }

  /**
   * Asserts that visitors_id uses no separator in recent_view_table.
   */
  public function testRecentViewTableVisitorsIdSeparatorIsEmpty(): void {
    $path = $this->moduleRoot . '/config/optional/views.view.visitors.yml';
    $separator = $this->getSeparator($path, 'recent_view_table', 'visitors_id');

    $this->assertSame(
      '',
      $separator,
      'visitors_id separator in recent_view_table must be empty to prevent ' .
      'comma-encoded hit-details URLs (e.g. /visitors/hits/2%2C011).'
    );
  }

  /**
   * Asserts that visitors_id uses no separator in referrer_table.
   */
  public function testReferrerTableVisitorsIdSeparatorIsEmpty(): void {
    $path = $this->moduleRoot . '/config/optional/views.view.visitors.yml';
    $separator = $this->getSeparator($path, 'referrer_table', 'visitors_id');

    $this->assertSame(
      '',
      $separator,
      'visitors_id separator in referrer_table must be empty to prevent ' .
      'comma-encoded hit-details URLs (e.g. /visitors/hits/2%2C011).'
    );
  }

  /**
   * Asserts that visitors_geoip recent_view_table uses no separator.
   */
  public function testGeoIpRecentViewTableVisitorsIdSeparatorIsEmpty(): void {
    $path = $this->moduleRoot
      . '/visitors_geoip/config/optional/views.view.visitors_geoip.yml';

    if (!file_exists($path)) {
      $this->markTestSkipped('visitors_geoip config not present.');
    }

    $separator = $this->getSeparator($path, 'recent_view_table', 'visitors_id');

    $this->assertSame(
      '',
      $separator,
      'visitors_id separator in visitors_geoip recent_view_table must be ' .
      'empty to prevent comma-encoded hit-details URLs.'
    );
  }

  /**
   * Asserts the details link path token is visitors_id (not another field).
   *
   * Guards against someone accidentally changing the token to a field that
   * uses a different formatter, re-introducing the separator problem.
   */
  public function testRecentViewTableDetailsLinkUsesVisitorsIdToken(): void {
    $path   = $this->moduleRoot . '/config/optional/views.view.visitors.yml';
    $config = Yaml::parseFile($path);

    $link_path = $config['display']['recent_view_table']['display_options']['fields']['nothing']['alter']['path'] ?? '';

    $this->assertStringContainsString(
      '{{ visitors_id }}',
      $link_path,
      'The details link path must use {{ visitors_id }} as the hit ID token.'
    );
  }

  /**
   * Asserts the referrer_table details link path token is visitors_id.
   */
  public function testReferrerTableDetailsLinkUsesVisitorsIdToken(): void {
    $path   = $this->moduleRoot . '/config/optional/views.view.visitors.yml';
    $config = Yaml::parseFile($path);

    $link_path = $config['display']['referrer_table']['display_options']['fields']['nothing']['alter']['path'] ?? '';

    $this->assertStringContainsString(
      '{{ visitors_id }}',
      $link_path,
      'The details link path must use {{ visitors_id }} as the hit ID token.'
    );
  }

}
