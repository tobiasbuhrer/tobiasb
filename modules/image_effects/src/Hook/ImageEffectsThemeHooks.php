<?php

declare(strict_types=1);

namespace Drupal\image_effects\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\image_effects\Component\ColorUtility;

/**
 * Theme hook implementations for image_effects.
 */
class ImageEffectsThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      // Render a color information box and string.
      'image_effects_color_detail' => [
        'variables' => [
          'color' => '#000000',
          'border' => FALSE,
          'border_color' => '#000000',
        ],
        'initial preprocess' => self::class . ':preprocessImageEffectsColorDetail',
      ],
      // Aspect switcher image effect - summary.
      'image_effects_aspect_switcher' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Background image effect - summary.
      'image_effects_background_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Brightness image effect - summary.
      'image_effects_brightness_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Color shift image effect - summary.
      'image_effects_color_shift_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Contrast image effect - summary.
      'image_effects_contrast_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Gaussian blur effect - summary.
      'image_effects_gaussian_blur_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // ImageMagick arguments effect - summary.
      'image_effects_imagemagick_arguments_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Mask image effect - summary.
      'image_effects_mask_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Mirror image effect - summary.
      'image_effects_mirror_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Opacity image effect - summary.
      'image_effects_opacity_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Pixelate image effect - summary.
      'image_effects_pixelate_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Relative crop image effect - summary.
      'image_effects_relative_crop_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Resize percentage image effect - summary.
      'image_effects_resize_percentage_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Rotate image effect - summary.
      'image_effects_rotate_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Scale and Smart Crop image effect - summary.
      'image_effects_scale_and_smart_crop_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Set canvas image effect - summary.
      'image_effects_set_canvas_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Set transparent color image effect - summary.
      'image_effects_set_transparent_color_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Smart Crop image effect - summary.
      'image_effects_smart_crop_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Render a preview of the Text Overlay in the image effect UI.
      'image_effects_text_overlay_preview' => [
        'variables' => ['success' => FALSE, 'preview' => []],
      ],
      // Text overlay image effect - summary.
      'image_effects_text_overlay_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Watemark image effect - summary.
      'image_effects_watermark_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Convolution image effect - summary.
      'image_effects_convolution_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Convolution sharpen image effect - summary.
      'image_effects_convolution_sharpen_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
      // Interlace image effect - summary.
      'image_effects_interlace_summary' => [
        'variables' => ['data' => NULL, 'effect' => []],
      ],
    ];
  }

  /**
   * Prepares variables to get a color info.
   *
   * Default template: image-effects-color-detail.html.twig.
   */
  public function preprocessImageEffectsColorDetail(array &$variables): void {
    $variables['#attached']['library'][] = 'image_effects/image_effects.admin.ui';
    if ($variables['color']) {
      if ($variables['border']) {
        if ($variables['border_color'] == 'matchLuma') {
          $variables['border_color'] = ColorUtility::matchLuma($variables['color']);
        }
        else {
          $variables['border_color'] = mb_substr($variables['border_color'], 0, 7);
        }
      }
      $variables['color_opacity'] = ColorUtility::rgbaToOpacity($variables['color']);
      $variables['color'] = mb_substr($variables['color'], 0, 7);
    }
  }

}
