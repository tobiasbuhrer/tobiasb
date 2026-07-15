<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/../../../visitors.install';

/**
 * Tests the hook_update_8232() function.
 *
 * @group visitors
 */
class HookUpdate8232Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'visitors',
    'user',
    'views',
    'charts',
    'block',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->legacyView();
  }

  /**
   * Tests the hook_update_8232() function.
   */
  public function testHookUpdate8232(): void {
    $view = $this->config('views.view.visitors');

    // Assert initial state - separators should be ','.
    $this->assertEquals(',', $view->get('display.recent_view_table.display_options.fields.visitor_id.separator'));
    $this->assertEquals(',', $view->get('display.referrer_table.display_options.fields.visitor_id.separator'));

    // Run the update function.
    visitors_update_8232();

    // Reload the configuration.
    $view = $this->config('views.view.visitors');

    // Assert updated state - separators should now be ''.
    $this->assertEquals('', $view->get('display.recent_view_table.display_options.fields.visitor_id.separator'));
    $this->assertEquals('', $view->get('display.referrer_table.display_options.fields.visitor_id.separator'));
  }

  /**
   * Create a legacy view with comma separators.
   */
  protected function legacyView(): void {
    $yaml = <<<YAML
langcode: en
status: true
dependencies:
  module:
    - charts
    - charts_chartjs
    - visitors
id: visitors
label: Visitors
module: views
description: 'Visitors web analytics reports.'
tag: ''
base_table: visitors
base_field: ''
display:
  default:
    id: default
    display_title: Default
    display_plugin: default
    position: 0
  recent_view_table:
    id: recent_view_table
    display_title: 'Recent View Table'
    display_plugin: page
    display_options:
      fields:
        visitor_id:
          id: visitor_id
          table: visitors
          field: visitor_id
          separator: ','
  referrer_table:
    id: referrer_table
    display_title: 'Referrer Table'
    display_plugin: page
    display_options:
      fields:
        visitor_id:
          id: visitor_id
          table: visitors
          field: visitor_id
          separator: ','
YAML;
    $view_array = Yaml::parse($yaml);
    $view = $this->config('views.view.visitors');
    $view->setData($view_array);
    $view->save();
  }

}
