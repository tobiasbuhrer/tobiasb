<?php

declare(strict_types=1);

namespace Drupal\image_effects\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigValueException;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleInterface;
use Drupal\image_effects\Install\Requirements\ImageEffectsRequirements;

/**
 * Hook implementations for image_effects.
 */
class ImageEffectsHooks {

  /**
   * Implements hook_image_effects_text_overlay_text_alter().
   */
  #[Hook('image_effects_text_overlay_text_alter')]
  public function textOverlayTextAlter(string &$text, ConfigurableImageEffectBase $image_effect): void {
    // Skip if the effect is not TextOverlayImageEffect or an alternative
    // implementation.
    if ($image_effect->getPluginId() !== "image_effects_text_overlay") {
      return;
    }

    // Get effect data.
    $effect_data = $image_effect->getConfiguration()['data'];

    // Strip HTML tags, if requested.
    if ($effect_data['text']['strip_tags'] === TRUE) {
      $text = strip_tags($text);
    }

    // Decode HTML entities, if requested.
    if ($effect_data['text']['decode_entities'] === TRUE) {
      $text = Html::decodeEntities($text);
    }

    // Convert case, if requested.
    if ($effect_data['text']['case_format']) {
      $method_map = [
        'upper' => 'mb_strtoupper',
        'lower' => 'mb_strtolower',
        'ucwords' => 'Drupal\Component\Utility\Unicode::ucwords',
        'ucfirst' => 'Drupal\Component\Utility\Unicode::ucfirst',
      ];
      $text = $method_map[$effect_data['text']['case_format']]($text);
    }

    // Limit the maximum number of characters.
    if ($effect_data['text']['maximum_chars'] > 0 && mb_strlen($text) > $effect_data['text']['maximum_chars']) {
      $text = mb_substr($text, 0, $effect_data['text']['maximum_chars']) . $effect_data['text']['excess_chars_text'];
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for image_style entities.
   *
   * This hook checks if the image style that is being saved contains any aspect
   * switcher effect that refers to the image style itself. If so, this is a
   * circular reference and we should raise an exception.
   */
  #[Hook('image_style_presave')]
  public function imageStylePresave(ImageStyleInterface $style): void {
    $effects = $style->getEffects();
    foreach ($effects as $effect) {
      $effect_data = $effect->getConfiguration()['data'];
      switch ($effect->getPluginId()) {
        case 'image_effects_aspect_switcher':
          if ($style->id() === $effect_data['landscape_image_style']) {
            throw new ConfigValueException("You can not select the {$style->label()} image style itself for the landscape style");
          }
          if ($style->id() === $effect_data['portrait_image_style']) {
            throw new ConfigValueException("You can not select the {$style->label()} image style itself for the portrait style");
          }
          break;

        default:
          continue 2;

      }
    }
  }

  /**
   * Implements hook_image_style_flush().
   *
   * This hook checks if the image style that is being flushed is used in any
   * aspect switcher effect. If so, the style that contains the aspect switcher
   * effect should be flushed as well.
   */
  #[Hook('image_style_flush')]
  public function imageStyleFlush(ImageStyleInterface $style, ?string $path = NULL): void {
    // Retrieve image styles that have an aspect switcher effect that contains
    // the style being flushed.
    $query = \Drupal::entityQuery('image_style');
    $query->condition('effects.*.id', 'image_effects_aspect_switcher');
    $group = $query->orConditionGroup()
      ->condition('effects.*.data.landscape_image_style', $style->id())
      ->condition('effects.*.data.portrait_image_style', $style->id());
    $image_style_ids = $query->condition($group)->execute();

    // Flush them all.
    foreach ($image_style_ids as $image_style_id) {
      $image_style = ImageStyle::load($image_style_id);
      $image_style->flush($path);
    }
  }

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $requirements = [];

    if ($checkFreeTypeSupport = ImageEffectsRequirements::checkFreeTypeSupport()) {
      $requirements = array_merge($requirements, $checkFreeTypeSupport);
    }

    return $requirements;
  }

}
