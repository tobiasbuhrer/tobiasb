<?php

declare(strict_types=1);

namespace Drupal\image_effects\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;

/**
 * Install time requirements for the Image Effects module.
 */
class ImageEffectsRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    $requirements = [];

    if ($checkFreeTypeSupport = self::checkFreeTypeSupport()) {
      $requirements = array_merge($requirements, $checkFreeTypeSupport);
    }

    return $requirements;
  }

  /**
   * Returns a warning requirement if GD FreeType support is missing.
   */
  public static function checkFreeTypeSupport(): ?array {
    // Check PHP GD2 FreeType support.
    if (function_exists('gd_info')) {
      $info = gd_info();
      if (!function_exists('imagettftext') || !isset($info["FreeType Support"])) {
        // No FreeType support, raise warning.
        return [
          'image_effects_gd_freetype' => [
            'title' => t('GD library FreeType support'),
            'value' => t('Not installed'),
            'severity' => RequirementSeverity::Warning,
            'description' => t('The GD Library for PHP is enabled, but was compiled without FreeType support. Image effects using fonts will not be available with the GD image toolkit.'),
          ],
        ];
      }
    }
    return NULL;
  }

}
