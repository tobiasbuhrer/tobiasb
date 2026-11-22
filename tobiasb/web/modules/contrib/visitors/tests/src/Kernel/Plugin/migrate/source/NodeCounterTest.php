<?php

declare(strict_types=1);

namespace Drupal\Tests\visitors\Kernel\Plugin\migrate\source;

use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;
use Drupal\visitors\Plugin\migrate\source\NodeCounter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Statistics node counter source plugin.
 *
 * @group visitors
 */
#[CoversClass(NodeCounter::class)]
#[Group('visitors')]
#[RunTestsInSeparateProcesses]
class NodeCounterTest extends MigrateSqlSourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'migrate',
    'visitors',
  ];

  /**
   * {@inheritdoc}
   */
  public static function providerSource() {
    $tests = [];

    $tests['one row']['source_data']['node_counter'] = [
      [
        'nid' => '1',
        'totalcount' => '5',
        'daycount' => '2',
        'timestamp' => '100',
      ],
    ];
    $tests['one row']['expected_data'] = [
      [
        'nid' => '1',
        'totalcount' => '5',
        'daycount' => '2',
        'timestamp' => '100',
      ],
    ];

    return $tests;
  }

}
