<?php

declare(strict_types=1);

namespace Drupal\photomap\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\leaflet_views\Plugin\views\style\LeafletMap;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Photomap style plugin.
 *
 * @ViewsStyle(
 *   id = "photomap_photomap",
 *   title = @Translation("Photomap"),
 *   help = @Translation("Photomap help."),
 *   theme = "views_style_photomap_photomap",
 *   display_types = {"normal"},
 * )
 */
final class Photomap extends LeafletMap {   

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowClass = TRUE;

}
