<?php

declare(strict_types=1);

namespace Drupal\visitors\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Implements hook_help().
 */
class HelpHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, $route_match) {
    switch ($route_name) {
      case 'help.page.visitors':
        $help = '<p><a href="https://git.drupalcode.org/project/visitors/-/commits/8.x-2.x"><img alt="coverage report" src="https://git.drupalcode.org/project/visitors/badges/8.x-2.x/coverage.svg" /></a> &nbsp;';
        $help .= '<a href="https://git.drupalcode.org/project/visitors/-/commits/8.x-2.x"><img alt="pipeline status" src="https://git.drupalcode.org/project/visitors/badges/8.x-2.x/pipeline.svg" /></a> &nbsp;';
        $help .= '<a href="https://www.drupal.org/project/visitors">Homepage</a> &nbsp;';
        $help .= '<a href="https://www.drupal.org/project/issues/visitors?version=any_8.x-">Issues</a></p>';
        $help .= '<p>' . $this->t('The Visitors module logs all visitors to your site and provides various statistics about them.') . '</p>';
        return [
          '#title' => $this->t('Visitors'),
          'description' => [
            '#markup' => $help,
          ],
        ];
    }
  }

}
