<?php

namespace Drupal\Tests\visitors\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\visitors\Controller\Report\ReportController;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Tests the visitors/{report} page.
 */
#[CoversClass(ReportController::class)]
#[CoversMethod(ReportController::class, 'nodeViews')]
#[Group('visitors')]
#[RunTestsInSeparateProcesses]
class NodeViewTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'visitors'];

  /**
   * Tests the html head links.
   */
  public function testHasReportAccess() {
    $user = $this->drupalCreateUser([
      'access visitors',
      'access content',
    ]);
    $this->drupalLogin($user);

    $this->visitReports(200);
  }

  /**
   * Tests that we store and retrieve multi-byte UTF-8 characters correctly.
   */
  protected function visitReports(int $status) {
    $this->drupalCreateContentType(['type' => 'page']);
    $this->drupalCreateNode();

    $this->drupalGet('node/1/visitors');
    $this->assertSession()->statusCodeEquals($status);
  }

}
