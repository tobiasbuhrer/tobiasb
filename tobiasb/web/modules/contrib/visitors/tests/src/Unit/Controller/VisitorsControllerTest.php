<?php

namespace Drupal\Tests\visitors\Unit\Controller;

use Drupal\Tests\UnitTestCase;
use Drupal\visitors\Controller\Visitors;
use Drupal\visitors_geoip\GeoIpCity;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ServerBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Unit tests for the HitDetails controller.
 */
#[CoversClass(Visitors::class)]
#[CoversMethod(Visitors::class, 'create')]
#[CoversMethod(Visitors::class, '__construct')]
#[CoversMethod(Visitors::class, 'doConfig')]
#[CoversMethod(Visitors::class, 'doCounter')]
#[CoversMethod(Visitors::class, 'doCustom')]
#[CoversMethod(Visitors::class, 'doDeviceDetect')]
#[CoversMethod(Visitors::class, 'doLanguage')]
#[CoversMethod(Visitors::class, 'doLocalTime')]
#[CoversMethod(Visitors::class, 'doLocation')]
#[CoversMethod(Visitors::class, 'doPerformance')]
#[CoversMethod(Visitors::class, 'doReferrer')]
#[CoversMethod(Visitors::class, 'doTime')]
#[CoversMethod(Visitors::class, 'doUrl')]
#[CoversMethod(Visitors::class, 'doVisitorId')]
#[CoversMethod(Visitors::class, 'getDefaultFields')]
#[CoversMethod(Visitors::class, 'track')]
#[CoversMethod(Visitors::class, 'getResponse')]
#[CoversMethod(Visitors::class, 'getImageContent')]
#[UsesClass(Visitors::class)]
#[Group('visitors')]
class VisitorsControllerTest extends UnitTestCase {

  /**
   * The Visitors controller under test.
   *
   * @var \Drupal\visitors\Controller\Visitors
   */
  protected $controller;

  /**
   * The visitors tracker.
   *
   * @var \Drupal\visitors\VisitorsTrackerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $visitorsTracker;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The date time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $dateTime;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * The counter.
   *
   * @var \Drupal\visitors\VisitorsCounterInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $counter;

  /**
   * The cookie.
   *
   * @var \Drupal\visitors\VisitorsCookieInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cookie;

  /**
   * The device.
   *
   * @var \Drupal\visitors\VisitorsDeviceInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $device;

  /**
   * The location.
   *
   * @var \Drupal\visitors\VisitorsLocationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $location;

  /**
   * The settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $settings;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $this->visitorsTracker = $this->createMock('\Drupal\visitors\VisitorsTrackerInterface');
    $container->set('visitors.tracker', $this->visitorsTracker);

    $this->configFactory = $this->createMock('\Drupal\Core\Config\ConfigFactoryInterface');
    $container->set('config.factory', $this->configFactory);

    $this->dateTime = $this->createMock('\Drupal\Component\Datetime\TimeInterface');
    $container->set('datetime.time', $this->dateTime);

    $this->logger = $this->createMock('\Psr\Log\LoggerInterface');
    $container->set('logger.channel.visitors', $this->logger);

    $this->counter = $this->createMock('\Drupal\visitors\VisitorsCounterInterface');
    $container->set('visitors.counter', $this->counter);

    $this->cookie = $this->createMock('\Drupal\visitors\VisitorsCookieInterface');
    $container->set('visitors.cookie', $this->cookie);

    $this->device = $this->createMock('\Drupal\visitors\VisitorsDeviceInterface');
    $container->set('visitors.device', $this->device);

    $this->location = $this->createMock('\Drupal\visitors\VisitorsLocationInterface');
    $container->set('visitors.location', $this->location);

    $this->settings = $this->createMock('\Drupal\Core\Config\ImmutableConfig');
    $this->configFactory->method('get')
      ->with('visitors.config')
      ->willReturn($this->settings);

    \Drupal::setContainer($container);

    $this->controller = Visitors::create($container);
  }

  /**
   * Tests the create() method.
   */
  public function testCreate() {
    $container = \Drupal::getContainer();
    $controller = Visitors::create($container);
    $this->assertInstanceOf(Visitors::class, $controller);
  }

  /**
   * Tests the construct() method.
   */
  public function testConstruct() {

    $controller = new Visitors(
      $this->configFactory,
      $this->dateTime,
      $this->logger,
      $this->counter,
      $this->cookie,
      $this->device,
      $this->location,
      $this->visitorsTracker,
      NULL,
    );
    $this->assertInstanceOf(Visitors::class, $controller);
  }

  /**
   * Tests the track() method.
   */
  public function testTrack() {
    $this->device->expects($this->once())
      ->method('hasLibrary')
      ->willReturn(TRUE);
    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $request->expects($this->once())
      ->method('getClientIp')
      ->willReturn('127.0.0.1');
    $query = new InputBag();
    $query->set('h', '1');
    $query->set('m', '2');
    $query->set('s', '3');
    $_cvar = [
      ['route', 'entity.node.canonical'],
      ['path', '/node/1'],
      ['server', 'localhost'],
      ['viewed', 'node:1'],
    ];
    $query->set('cvar', json_encode($_cvar));
    $request->server = new ServerBag();
    $request->server->set('HTTP_USER_AGENT', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GoogleBot/2.1; +http://www.google.com/bot.html) Chrome/W.X.Y.Z Safari/537.36');
    $request->method('getLanguages')->willReturn([]);
    $request->query = $query;

    $this->settings->expects($this->once())
      ->method('get')
      ->with('bot_retention_log')
      ->willReturn(0);

    $response = $this->controller->track($request);
    $this->assertSame('', $response->getContent());
    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('must-revalidate, no-cache, no-store, private', $response->headers->get('Cache-Control'));
    $this->assertSame('no-cache', $response->headers->get('Pragma'));
    $this->assertSame('0', $response->headers->get('Expires'));
    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('must-revalidate, no-cache, no-store, private', $response->headers->get('Cache-Control'));
    $this->assertSame('no-cache', $response->headers->get('Pragma'));
    $this->assertSame('0', $response->headers->get('Expires'));
  }

  /**
   * Tests the track() method.
   */
  public function testTrackNoDeviceLibrary() {
    $this->device->expects($this->once())
      ->method('hasLibrary')
      ->willReturn(FALSE);
    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $request->expects($this->once())
      ->method('getClientIp')
      ->willReturn('127.0.0.1');
    $query = new InputBag();
    $query->set('h', '1');
    $query->set('m', '2');
    $query->set('s', '3');
    $_cvar = [
      ['route', 'entity.node.canonical'],
      ['path', '/node/1'],
      ['server', 'localhost'],
      ['viewed', 'node:1'],
    ];
    $query->set('cvar', json_encode($_cvar));
    $request->server = new ServerBag();
    $request->server->set('HTTP_USER_AGENT', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GoogleBot/2.1; +http://www.google.com/bot.html) Chrome/W.X.Y.Z Safari/537.36');
    $request->method('getLanguages')->willReturn(['en_US']);
    $request->query = $query;

    $this->settings->expects($this->once())
      ->method('get')
      ->with('bot_retention_log')
      ->willReturn(0);

    $this->location->expects($this->once())
      ->method('isValidCountryCode')
      ->with('US')
      ->willReturn(TRUE);
    $this->location->expects($this->once())
      ->method('getContinent')
      ->with('US')
      ->willReturn('NA');

    $response = $this->controller->track($request);
    $this->assertSame('', $response->getContent());
    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('must-revalidate, no-cache, no-store, private', $response->headers->get('Cache-Control'));
    $this->assertSame('no-cache', $response->headers->get('Pragma'));
    $this->assertSame('0', $response->headers->get('Expires'));
  }

  /**
   * Tests track() with a valid 3-letter language code.
   */
  public function testTrackWithValidThreeLetterLanguage() {
    $this->device->expects($this->once())
      ->method('hasLibrary')
      ->willReturn(FALSE);
    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $request->expects($this->once())
      ->method('getClientIp')
      ->willReturn('127.0.0.1');
    $query = new InputBag();
    $query->set('h', '1');
    $query->set('m', '2');
    $query->set('s', '3');
    $_cvar = [
      ['route', 'entity.node.canonical'],
      ['path', '/node/1'],
      ['server', 'localhost'],
      ['viewed', 'node:1'],
    ];
    $query->set('cvar', json_encode($_cvar));
    $request->server = new ServerBag();
    $request->method('getLanguages')->willReturn(['gsw_CH']);
    $request->query = $query;

    $this->settings->expects($this->once())
      ->method('get')
      ->with('bot_retention_log')
      ->willReturn(0);

    $this->location->expects($this->once())
      ->method('isValidCountryCode')
      ->with('CH')
      ->willReturn(FALSE);

    $this->visitorsTracker->expects($this->once())
      ->method('writeLog')
      ->with($this->callback(function (array $fields): bool {
        return ($fields['language'] ?? NULL) === 'gsw';
      }))
      ->willReturn(1);

    $this->controller->track($request);
  }

  /**
   * Tests track() with an invalid language code length.
   */
  public function testTrackWithInvalidLanguageLength() {
    $this->device->expects($this->once())
      ->method('hasLibrary')
      ->willReturn(FALSE);
    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $request->expects($this->once())
      ->method('getClientIp')
      ->willReturn('127.0.0.1');
    $query = new InputBag();
    $query->set('h', '1');
    $query->set('m', '2');
    $query->set('s', '3');
    $_cvar = [
      ['route', 'entity.node.canonical'],
      ['path', '/node/1'],
      ['server', 'localhost'],
      ['viewed', 'node:1'],
    ];
    $query->set('cvar', json_encode($_cvar));
    $request->server = new ServerBag();
    $request->method('getLanguages')->willReturn(['english_US']);
    $request->query = $query;

    $this->settings->expects($this->once())
      ->method('get')
      ->with('bot_retention_log')
      ->willReturn(0);

    $this->location->expects($this->once())
      ->method('isValidCountryCode')
      ->with('US')
      ->willReturn(FALSE);

    $this->visitorsTracker->expects($this->once())
      ->method('writeLog')
      ->with($this->callback(function (array $fields): bool {
        return !array_key_exists('language', $fields);
      }))
      ->willReturn(1);

    $this->controller->track($request);
  }

  /**
   * Tests track() with a one-letter language code.
   */
  public function testTrackWithSingleLetterLanguageCode() {
    $this->device->expects($this->once())
      ->method('hasLibrary')
      ->willReturn(FALSE);
    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $request->expects($this->once())
      ->method('getClientIp')
      ->willReturn('127.0.0.1');
    $query = new InputBag();
    $query->set('h', '1');
    $query->set('m', '2');
    $query->set('s', '3');
    $_cvar = [
      ['route', 'entity.node.canonical'],
      ['path', '/node/1'],
      ['server', 'localhost'],
      ['viewed', 'node:1'],
    ];
    $query->set('cvar', json_encode($_cvar));
    $request->server = new ServerBag();
    $request->method('getLanguages')->willReturn(['e_US']);
    $request->query = $query;

    $this->settings->expects($this->once())
      ->method('get')
      ->with('bot_retention_log')
      ->willReturn(0);

    $this->location->expects($this->once())
      ->method('isValidCountryCode')
      ->with('US')
      ->willReturn(FALSE);

    $this->visitorsTracker->expects($this->once())
      ->method('writeLog')
      ->with($this->callback(function (array $fields): bool {
        return !array_key_exists('language', $fields);
      }))
      ->willReturn(1);

    $this->controller->track($request);
  }

  /**
   * Tests the track() method.
   */
  public function testTrackBot() {
    $this->device->expects($this->once())
      ->method('hasLibrary')
      ->willReturn(TRUE);
    $this->device->expects($this->once())
      ->method('doDeviceFields')
      ->willReturnCallback(function (array &$fields, string $user_agent, ?array $server = NULL): void {
        $fields['bot'] = TRUE;
      });
    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $request->expects($this->once())
      ->method('getClientIp')
      ->willReturn('127.0.0.1');
    $query = new InputBag();
    $query->set('h', '1');
    $query->set('m', '2');
    $query->set('s', '3');
    $_cvar = [
      ['route', 'entity.node.canonical'],
      ['path', '/node/1'],
      ['server', 'localhost'],
      ['viewed', 'node:1'],
    ];
    $query->set('cvar', json_encode($_cvar));
    $request->server = new ServerBag();
    $request->server->set('HTTP_USER_AGENT', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GoogleBot/2.1; +http://www.google.com/bot.html) Chrome/W.X.Y.Z Safari/537.36');
    $request->method('getLanguages')->willReturn([]);
    $request->query = $query;

    $this->settings->expects($this->once())
      ->method('get')
      ->with('bot_retention_log')
      ->willReturn(-1);

    $response = $this->controller->track($request);
    $this->assertSame('', $response->getContent());
    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('must-revalidate, no-cache, no-store, private', $response->headers->get('Cache-Control'));
    $this->assertSame('no-cache', $response->headers->get('Pragma'));
    $this->assertSame('0', $response->headers->get('Expires'));
  }

  /**
   * Tests the track() method.
   */
  public function testTrackNoLocal() {
    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $request->expects($this->once())
      ->method('getClientIp')
      ->willReturn('127.0.0.1');
    $query = new InputBag();
    $_cvar = [
      ['route', 'entity.node.canonical'],
      ['path', '/node/1'],
      ['server', 'localhost'],
      ['viewed', 'node:1'],
    ];
    $query->set('cvar', json_encode($_cvar));
    $request->server = new ServerBag();

    $request->method('getLanguages')->willReturn([]);
    $request->query = $query;

    $this->settings->expects($this->once())
      ->method('get')
      ->with('bot_retention_log')
      ->willReturn(0);

    $response = $this->controller->track($request);
    $this->assertSame('', $response->getContent());
    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('must-revalidate, no-cache, no-store, private', $response->headers->get('Cache-Control'));
    $this->assertSame('no-cache', $response->headers->get('Pragma'));
    $this->assertSame('0', $response->headers->get('Expires'));
  }

  /**
   * Tests location with geoip; location null.
   */
  public function testTrackGeoIp() {
    $container = \Drupal::getContainer();

    $city = new GeoIpCity('NA', 'US', 'CA', 'Chicago', 37.751, -97.822);

    $geoip = $this->createMock('\Drupal\visitors_geoip\VisitorsGeoIpInterface');
    $geoip->expects($this->once())
      ->method('city')
      ->with('127.0.0.1')
      ->willReturn($city);
    $container->set('visitors_geoip.lookup', $geoip);

    $controller = Visitors::create($container);

    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $request->expects($this->once())
      ->method('getClientIp')
      ->willReturn('127.0.0.1');

    $query = new InputBag();
    $query->set('h', '1');
    $query->set('m', '2');
    $query->set('s', '3');
    $_cvar = [
      ['route', 'entity.node.canonical'],
      ['path', '/node/1'],
      ['server', 'localhost'],
      ['viewed', 'node:1'],
    ];
    $query->set('cvar', json_encode($_cvar));
    $request->server = new ServerBag();

    $request->method('getLanguages')->willReturn([]);
    $request->query = $query;

    $this->settings->expects($this->once())
      ->method('get')
      ->with('bot_retention_log')
      ->willReturn(0);

    $this->visitorsTracker->expects($this->once())
      ->method('writeLog')
      ->with($this->callback(function (array $fields): bool {
        return ($fields['location_continent'] ?? NULL) === 'NA'
          && ($fields['location_country'] ?? NULL) === 'US'
          && ($fields['location_region'] ?? NULL) === 'CA'
          && ($fields['location_city'] ?? NULL) === 'Chicago'
          && ($fields['location_latitude'] ?? NULL) === 37.751
          && ($fields['location_longitude'] ?? NULL) === -97.822;
      }))
      ->willReturn(1);

    $response = $controller->track($request);
    $this->assertSame('', $response->getContent());
  }

  /**
   * Tests location with geoip; location null.
   */
  public function testTrackGeoIpNull() {
    $container = \Drupal::getContainer();

    $geoip = $this->createMock('\Drupal\visitors_geoip\VisitorsGeoIpInterface');
    $container->set('visitors_geoip.lookup', $geoip);

    $controller = Visitors::create($container);

    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $request->expects($this->once())
      ->method('getClientIp')
      ->willReturn('127.0.0.1');

    $query = new InputBag();
    $query->set('h', '1');
    $query->set('m', '2');
    $query->set('s', '3');
    $_cvar = [
      ['route', 'entity.node.canonical'],
      ['path', '/node/1'],
      ['server', 'localhost'],
      ['viewed', 'node:1'],
    ];
    $query->set('cvar', json_encode($_cvar));
    $request->server = new ServerBag();

    $request->method('getLanguages')->willReturn([]);
    $request->query = $query;

    $this->settings->expects($this->once())
      ->method('get')
      ->with('bot_retention_log')
      ->willReturn(0);

    $this->visitorsTracker->expects($this->once())
      ->method('writeLog')
      ->with($this->callback(function (array $fields): bool {
        return !array_key_exists('location_continent', $fields)
          && !array_key_exists('location_country', $fields)
          && !array_key_exists('location_region', $fields)
          && !array_key_exists('location_city', $fields)
          && !array_key_exists('location_latitude', $fields)
          && !array_key_exists('location_longitude', $fields);
      }))
      ->willReturn(1);

    $response = $controller->track($request);
    $this->assertSame('', $response->getContent());
  }

  /**
   * Tests the track() method with send_image=true.
   */
  public function testTrackWithSendImageTrue() {
    $this->device->expects($this->once())
      ->method('hasLibrary')
      ->willReturn(TRUE);
    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $request->expects($this->once())
      ->method('getClientIp')
      ->willReturn('127.0.0.1');
    $query = new InputBag();
    $query->set('send_image', '1');
    $query->set('h', '1');
    $query->set('m', '2');
    $query->set('s', '3');
    $_cvar = [
      ['route', 'entity.node.canonical'],
      ['path', '/node/1'],
      ['server', 'localhost'],
      ['viewed', 'node:1'],
    ];
    $query->set('cvar', json_encode($_cvar));
    $request->server = new ServerBag();
    $request->server->set('HTTP_USER_AGENT', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GoogleBot/2.1; +http://www.google.com/bot.html) Chrome/W.X.Y.Z Safari/537.36');
    $request->method('getLanguages')->willReturn([]);
    $request->query = $query;

    $this->settings->expects($this->once())
      ->method('get')
      ->with('bot_retention_log')
      ->willReturn(0);

    $response = $this->controller->track($request);

    // Verify the GIF image content is returned.
    $expected_content = hex2bin('47494638396101000100800000000000FFFFFF21F9040100000000002C00000000010001000002024401003B');
    $this->assertSame($expected_content, $response->getContent());
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('image/gif', $response->headers->get('Content-Type'));
    $this->assertSame(strlen($expected_content), (int) $response->headers->get('Content-Length'));
    $this->assertSame('must-revalidate, no-cache, no-store, private', $response->headers->get('Cache-Control'));
    $this->assertSame('no-cache', $response->headers->get('Pragma'));
    $this->assertSame('0', $response->headers->get('Expires'));
  }

  /**
   * Tests the track() method with send_image=false.
   */
  public function testTrackWithSendImageFalse() {
    $this->device->expects($this->once())
      ->method('hasLibrary')
      ->willReturn(TRUE);
    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $request->expects($this->once())
      ->method('getClientIp')
      ->willReturn('127.0.0.1');
    $query = new InputBag();
    $query->set('send_image', '0');
    $query->set('h', '1');
    $query->set('m', '2');
    $query->set('s', '3');
    $_cvar = [
      ['route', 'entity.node.canonical'],
      ['path', '/node/1'],
      ['server', 'localhost'],
      ['viewed', 'node:1'],
    ];
    $query->set('cvar', json_encode($_cvar));
    $request->server = new ServerBag();
    $request->server->set('HTTP_USER_AGENT', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GoogleBot/2.1; +http://www.google.com/bot.html) Chrome/W.X.Y.Z Safari/537.36');
    $request->method('getLanguages')->willReturn([]);
    $request->query = $query;

    $this->settings->expects($this->once())
      ->method('get')
      ->with('bot_retention_log')
      ->willReturn(0);

    $response = $this->controller->track($request);

    // Verify empty content and 204 status are returned.
    $this->assertSame('', $response->getContent());
    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('must-revalidate, no-cache, no-store, private', $response->headers->get('Cache-Control'));
    $this->assertSame('no-cache', $response->headers->get('Pragma'));
    $this->assertSame('0', $response->headers->get('Expires'));
  }

  /**
   * Tests the getImageContent() method.
   */
  public function testGetImageContent() {
    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('getImageContent');

    $content = $method->invoke($this->controller);
    $expected_content = hex2bin('47494638396101000100800000000000FFFFFF21F9040100000000002C00000000010001000002024401003B');

    $this->assertSame($expected_content, $content);
    $this->assertStringStartsWith('GIF89a', $content);
  }

}
