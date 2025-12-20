<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\image_effects\Plugin\Attribute\ColorSelector;

/**
 * Plugin manager for ColorSelector plugins.
 */
class ColorSelectorPluginManager extends ImageEffectsPluginManagerBase implements FallbackPluginManagerInterface {

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct(
      "Plugin/image_effects/ColorSelector",
      $namespaces,
      $module_handler,
      $configFactory,
      ColorSelector::class,
    );
    $this->alterInfo("image_effects_color_selector_plugin_info");
    $this->setCacheBackend($cache_backend, "image_effects_color_selector_plugins");
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'color_selector';
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) {
    return 'html_color';
  }

}
