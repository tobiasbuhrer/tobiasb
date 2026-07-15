<?php

namespace Drupal\Tests\visitors\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\visitors\Service\LanguageService;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the CookieService class.
 *
 * @coversDefaultClass \Drupal\visitors\Service\LanguageService
 * @uses \Drupal\visitors\Service\LanguageService
 * @group visitors
 */
class LanguageServiceTest extends UnitTestCase {


  /**
   * The language service.
   *
   * @var \Drupal\visitors\Service\LanguageService
   */
  protected $language;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();

    \Drupal::setContainer($container);

    $this->language = new LanguageService($string_translation);
  }

  /**
   * Tests the construct method.
   *
   * @covers ::__construct
   */
  public function testConstruct() {
    $language = new LanguageService($this->getStringTranslationStub());
    $this->assertInstanceOf(LanguageService::class, $language);
  }

  /**
   * Tests the getLanguageLabel method.
   *
   * @covers ::getLanguageLabel
   */
  public function testGetLanguageLabel() {
    // Existing ISO 639-1 (2-letter) codes must still resolve correctly.
    $this->assertSame('Yoruba', (string) $this->language->getLanguageLabel('yo'));
    $this->assertSame('English', (string) $this->language->getLanguageLabel('en'));
    $this->assertSame('Croatian', (string) $this->language->getLanguageLabel('hr'));

    // ISO 639-2 (3-letter) equivalents of the above.
    $this->assertSame('Yoruba', (string) $this->language->getLanguageLabel('yor'));
    $this->assertSame('English', (string) $this->language->getLanguageLabel('eng'));
    $this->assertSame('Croatian', (string) $this->language->getLanguageLabel('hrv'));

    // Bibliographic (B) ISO 639-2 variants.
    $this->assertSame('French', (string) $this->language->getLanguageLabel('fre'));
    $this->assertSame('German', (string) $this->language->getLanguageLabel('ger'));

    // Terminological (T) ISO 639-2 variants resolve to the same label.
    $this->assertSame('French', (string) $this->language->getLanguageLabel('fra'));
    $this->assertSame('German', (string) $this->language->getLanguageLabel('deu'));

    // ISO 639-2-only codes (no ISO 639-1 equivalent).
    $this->assertSame(
      'Montenegrin',
      (string) $this->language->getLanguageLabel('cnr')
    );
    $this->assertSame(
      'Swiss German',
      (string) $this->language->getLanguageLabel('gsw')
    );
    $this->assertSame(
      'Klingon',
      (string) $this->language->getLanguageLabel('tlh')
    );

    // Input is normalized: uppercase and surrounding whitespace are accepted.
    $this->assertSame('English', (string) $this->language->getLanguageLabel('ENG'));
    $this->assertSame('French', (string) $this->language->getLanguageLabel(' fr '));

    // Unknown codes return 'Unknown' regardless of length.
    $this->assertSame('Unknown', (string) $this->language->getLanguageLabel('zz'));
    $this->assertSame('Unknown', (string) $this->language->getLanguageLabel('zzz'));
  }

}
