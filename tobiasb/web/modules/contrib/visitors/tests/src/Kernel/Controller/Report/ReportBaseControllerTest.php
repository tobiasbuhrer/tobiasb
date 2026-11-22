<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Kernel\Controller\Report;

use Drupal\KernelTests\KernelTestBase;
use Drupal\visitors\Controller\Report\ReportBaseController;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests embedded report views.
 */
#[Group('visitors')]
#[RunTestsInSeparateProcesses]
class ReportBaseControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'views',
  ];

  /**
   * A missing view renders empty instead of causing an error.
   */
  public function testMissingViewIsEmpty(): void {
    $controller = new ReportBaseController();
    $build = $controller->renderViews([
      'region' => [
        '#view_id'      => 'visitors_geoip',
        '#view_display' => 'region_table',
      ],
    ]);

    $html = (string) $this->container->get('renderer')->renderRoot($build);
    $this->assertSame('<div></div>', $html);
  }

}
