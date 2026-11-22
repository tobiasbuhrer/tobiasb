<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Unit\Form;

use Drupal\Tests\UnitTestCase;
use Drupal\visitors\Form\Referer;
use Drupal\visitors\VisitorsReportInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use Drupal\visitors\Form\DateFilter;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Referer form test.
 */
#[CoversClass(Referer::class)]
#[CoversMethod(Referer::class, 'getFormId')]
#[CoversMethod(Referer::class, 'buildForm')]
#[CoversMethod(Referer::class, 'setSessionRefererType')]
#[CoversMethod(Referer::class, 'submitForm')]
#[UsesClass(Referer::class)]
#[UsesClass(DateFilter::class)]
#[Group('visitors')]
class RefererTest extends UnitTestCase {

  /**
   * The form.
   *
   * @var \Drupal\visitors\Form\Referer
   */
  protected $form;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The date range service.
   *
   * @var \Drupal\visitors\Service\DateRangeService|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $dateRangeService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $date_formatter = $this->createMock('Drupal\Core\Datetime\DateFormatterInterface');
    $container->set('date.formatter', $date_formatter);

    $database = $this->createMock('Drupal\Core\Database\Connection');
    $container->set('database', $database);

    $this->languageManager = $this->createMock('Drupal\Core\Language\LanguageManagerInterface');
    $container->set('language_manager', $this->languageManager);

    $this->dateRangeService = $this->createMock('Drupal\visitors\Service\DateRangeService');
    $container->set('visitors.date_range', $this->dateRangeService);

    \Drupal::setContainer($container);

    $this->form = Referer::create($container);
  }

  /**
   * Test the form id.
   */
  public function testGetFormId() {
    $this->assertEquals('visitors_referer_form', $this->form->getFormId());
  }

  /**
   * Test the form.
   */
  public function testBuildForm() {

    $current_language = $this->createMock('Drupal\Core\Language\LanguageInterface');
    $current_language->expects($this->any())
      ->method('getId')
      ->willReturn('en');
    $this->languageManager->expects($this->any())
      ->method('getCurrentLanguage')
      ->willReturn($current_language);

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form = $this->form->buildForm([], $form_state);

    $this->assertIsArray($form);
    $this->assertCount(3, $form);
    $this->assertArrayHasKey('visitors_date_filter', $form);
    $this->assertArrayHasKey('visitors_referer', $form);
    $this->assertArrayHasKey('submit', $form);
  }

  /**
   * Test the default referer.
   */
  public function testBuildFormReferer() {
    unset($_SESSION['referer_type']);
    $current_language = $this->createMock('Drupal\Core\Language\LanguageInterface');
    $current_language->expects($this->any())
      ->method('getId')
      ->willReturn('en');
    $this->languageManager->expects($this->any())
      ->method('getCurrentLanguage')
      ->willReturn($current_language);

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form = $this->form->buildForm([], $form_state);

    $this->assertIsArray($form);
    $this->assertCount(3, $form);
    $this->assertArrayHasKey('visitors_date_filter', $form);
    $this->assertArrayHasKey('visitors_referer', $form);
    $this->assertArrayHasKey('submit', $form);

    $this->assertEquals($_SESSION['referer_type'], VisitorsReportInterface::REFERER_TYPE_EXTERNAL_PAGES);
  }

  /**
   * Tests submitForm.
   */
  public function testSubmitForm() {
    $date_to = $this->createMock('Drupal\Core\Datetime\DrupalDateTime');

    $date_from = $this->createMock('Drupal\Core\Datetime\DrupalDateTime');

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'referer_type' => 'internal',
      ]);
    $form_state->expects($this->exactly(3))
      ->method('getValue')
      ->willReturn($date_to, $date_from, 'period');

    $this->form->submitForm($form, $form_state);
  }

}
