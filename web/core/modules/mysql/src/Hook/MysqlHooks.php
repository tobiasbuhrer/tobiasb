<?php

namespace Drupal\mysql\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for mysql.
 */
class MysqlHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.mysql':
        $output = '';
        $output .= '<h2>' . t('About') . '</h2>';
        $output .= '<p>' . t('The MySQL module provides the connection between Drupal and a MySQL, MariaDB or equivalent database. For more information, see the <a href=":mysql">online documentation for the MySQL module</a>.', [
          ':mysql' => 'https://www.drupal.org/docs/core-modules-and-themes/core-modules/mysql-module',
        ]) . '</p>';
        return $output;
    }
  }

}
