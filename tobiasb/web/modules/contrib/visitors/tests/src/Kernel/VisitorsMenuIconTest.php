<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Visitors administration menu icon.
 */
#[Group('visitors')]
#[RunTestsInSeparateProcesses]
class VisitorsMenuIconTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'charts',
    'visitors',
  ];

  /**
   * Tests the top-level Visitors link points at the visitors icon pack.
   */
  public function testVisitorsIndexIcon(): void {
    $menu_link_manager = $this->container->get('plugin.manager.menu.link');
    $menu_link_manager->rebuild();
    $definition = $menu_link_manager->getDefinition('visitors.index');

    $this->assertSame('visitors', $definition['options']['icon']['pack_id']);
    $this->assertSame('visitors', $definition['options']['icon']['icon_id']);

    if (!$this->container->has('plugin.manager.icon_pack')) {
      return;
    }

    $icon = $this->container->get('plugin.manager.icon_pack')
      ->getIcon('visitors:visitors');
    $this->assertNotNull($icon);
    $this->assertSame('visitors', $icon->getPackId());
    $this->assertSame('visitors', $icon->getIconId());
  }

}
