<?php

declare(strict_types=1);

namespace Drupal\visitors\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Implements hook_cron().
 */
class CronHooks {

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public static function cron(): void {
    \Drupal::service('visitors.cron')->execute();
  }

}
