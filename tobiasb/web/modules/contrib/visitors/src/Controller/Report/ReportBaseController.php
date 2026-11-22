<?php

namespace Drupal\visitors\Controller\Report;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\views\Views;

/**
 * Report base controller.
 */
class ReportBaseController extends ControllerBase implements TrustedCallbackInterface {

  /**
   * Render the registered blocks as output.
   *
   * @param array $blocks
   *   An array of block items formatted for rendering a view.
   * @param string|null $class
   *   An optional class to add to the wrapper div.
   * @param array|null $args
   *   An optional array of arguments to pass to the view.
   */
  public function renderViews(array $blocks, $class = NULL, $args = NULL) {
    $output = [];
    if ($args !== NULL && !is_array($args)) {
      $args = [$args];
    }
    // Render each block element.
    foreach ($blocks as $block) {
      if (empty($block['#view_id'])) {

        $build = $block;
      }
      else {
        $view_id = $block['#view_id'];
        $display_id = $block['#view_display'];
        $build = [
          '#pre_render'  => [
            [static::class, 'preRenderEmbeddedView'],
          ],
          '#name'        => $view_id,
          '#display_id'  => $display_id,
          '#arguments'   => $args ?? [],
        ];
      }
      if (!isset($build['#attributes']) && isset($block['#attributes'])) {
        $build['#attributes'] = $block['#attributes'];
      }

      $output[] = $build;
    }
    $prefix = '<div>';
    if (!empty($class)) {
      $prefix = '<div class="' . $class . '">';
    }
    return [
      '#prefix'   => $prefix,
      'blocks'    => $output,
      '#suffix'   => '</div>',
    ];
  }

  /**
   * Builds a view element when the view exists and is accessible.
   *
   * The replaced helper returned an empty array for a missing or
   * inaccessible view. The view render element throws instead.
   *
   * @param array $element
   *   The placeholder render element.
   *
   * @return array
   *   The view render element, or an empty array.
   */
  public static function preRenderEmbeddedView(array $element): array {
    $view = Views::getView($element['#name']);
    if (!$view || !$view->access($element['#display_id'])) {
      return [];
    }

    $view_element = [
      '#type'       => 'view',
      '#name'       => $element['#name'],
      '#display_id' => $element['#display_id'],
      '#arguments'  => $element['#arguments'] ?? [],
    ];
    if (isset($element['#attributes'])) {
      $view_element['#attributes'] = $element['#attributes'];
    }
    $element['view'] = $view_element;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderEmbeddedView'];
  }

}
