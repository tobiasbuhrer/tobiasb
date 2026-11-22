<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

require_once __DIR__ . '/../../../visitors.install';

/**
 * Tests the hook_update_8234() function.
 */
#[Group('visitors')]
#[RunTestsInSeparateProcesses]
class HookUpdate8234Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'visitors',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->legacyConfig();
  }

  /**
   * Tests that the update adds a disabled client debug setting.
   */
  public function testHookUpdate8234(): void {
    $config = $this->config('visitors.config');
    $this->assertNull($config->get('client_debug'));

    visitors_update_8234();

    $config = $this->config('visitors.config');
    $this->assertFalse($config->get('client_debug'));
  }

  /**
   * Set configuration from before the client debug setting existed.
   */
  protected function legacyConfig() {
    $yaml = <<<YAML
flush_log_timer: 0
bot_retention_log: 0
items_per_page: 10
theme: admin
disable_tracking: false
track:
  userid: true
counter:
  enabled: true
  entity_types:
    - 'node'
  display_max_age: 3600
visibility:
  request_path_mode: 0
  request_path_pages: ''
  user_role_mode: 0
  user_role_roles: {}
  user_account_mode: 1
  exclude_user1: false
script_type: minified
YAML;
    $values = Yaml::decode($yaml);
    $config = $this->config('visitors.config');
    $config->setData($values);
    $config->save();
  }

}
