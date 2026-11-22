<?php

namespace Drupal\Tests\visitors\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\visitors\Controller\Report\ReportController;
use PHPUnit\Framework\Attributes\CoversMethod;
use Drupal\visitors\Form\Settings;

/**
 * Tests the visitors/{report} page.
 */
#[CoversClass(ReportController::class)]
#[CoversMethod(ReportController::class, 'performance')]
#[CoversMethod(ReportController::class, 'location')]
#[CoversMethod(ReportController::class, 'device')]
#[CoversMethod(ReportController::class, 'software')]
#[CoversMethod(ReportController::class, 'time')]
#[CoversMethod(ReportController::class, 'topHost')]
#[CoversMethod(ReportController::class, 'topPages')]
#[CoversMethod(ReportController::class, 'recentHost')]
#[CoversMethod(Settings::class, 'buildForm')]
#[UsesClass(ReportController::class)]
#[Group('visitors')]
#[RunTestsInSeparateProcesses]
class VisitorsViewTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['visitors', 'node'];

  /**
   * Tests the html head links.
   */
  public function testHasReportAccess() {
    $user = $this->drupalCreateUser([
      'access visitors',
    ]);
    $this->drupalLogin($user);

    $this->visitReports(200);
  }

  /**
   * Tests the Link header.
   */
  public function testNoReportAccess() {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->visitReports(403);
  }

  /**
   * Tests Visitors Settings form access.
   */
  public function testVisitorsSettingsForm403() {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);
    $this->drupalGet('/admin/config/system/visitors');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests Visitors Settings form access.
   */
  public function testVisitorsSettingsForm200() {
    $user = $this->drupalCreateUser([
      'administer site configuration',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('/admin/config/system/visitors');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that we store and retrieve multi-byte UTF-8 characters correctly.
   */
  protected function visitReports(int $status) {
    $this->drupalGet('/visitors');
    $this->assertSession()->statusCodeEquals($status);

    $this->drupalGet('/visitors/host');
    $this->assertSession()->statusCodeEquals($status);

    $this->drupalGet('/visitors/hits');
    $this->assertSession()->statusCodeEquals($status);

    $this->drupalGet('/visitors/referrers');
    $this->assertSession()->statusCodeEquals($status);

    $this->drupalGet('/visitors/pages');
    $this->assertSession()->statusCodeEquals($status);

    $this->drupalGet('/visitors/performance');
    $this->assertSession()->statusCodeEquals($status);

    $this->drupalGet('/visitors/location');
    $this->assertSession()->statusCodeEquals($status);

    $this->drupalGet('/visitors/device');
    $this->assertSession()->statusCodeEquals($status);

    $this->drupalGet('/visitors/software');
    $this->assertSession()->statusCodeEquals($status);

    $this->drupalGet('/visitors/times');
    $this->assertSession()->statusCodeEquals($status);
  }

}
