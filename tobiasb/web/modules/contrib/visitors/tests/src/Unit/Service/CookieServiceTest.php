<?php

namespace Drupal\Tests\visitors\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\visitors\Service\CookieService;
use Symfony\Component\HttpFoundation\InputBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Tests the CookieService class.
 */
#[CoversClass(CookieService::class)]
#[CoversMethod(CookieService::class, 'getId')]
#[CoversMethod(CookieService::class, '__construct')]
#[Group('visitors')]
class CookieServiceTest extends UnitTestCase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->requestStack = $this->createMock('\Symfony\Component\HttpFoundation\RequestStack');

  }

  /**
   * Tests the getId method.
   */
  public function testGetId() {

    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $cookies = new InputBag();
    $cookies->set('_pk_id_', '123.456');
    $request->cookies = $cookies;

    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $cookieService = new CookieService($this->requestStack);
    $this->assertEquals('123', $cookieService->getId());
  }

  /**
   * Tests the getId method when null.
   */
  public function testGetNullId() {

    $request = $this->createMock('\Symfony\Component\HttpFoundation\Request');
    $cookies = new InputBag();
    $request->cookies = $cookies;

    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $cookieService = new CookieService($this->requestStack);
    $this->assertEquals(NULL, $cookieService->getId());
  }

}
