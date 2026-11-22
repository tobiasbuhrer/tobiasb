<?php

namespace Drupal\visitors_geoip\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for visitors_geoip.
 */
class VisitorsGeoipHooks {
  use StringTranslationTrait;
  /**
   * @file
   * Visitors GeoIP module file.
   */

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data) {
    $data['visitors']['location_region'] = [
      'title' => $this->t('Region'),
      'help' => $this->t('The region of the visitor.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['visitors']['location_city'] = [
      'title' => $this->t('City'),
      'help' => $this->t('The city of the visitor.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
  }

}
