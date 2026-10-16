<?php

declare(strict_types=1);

namespace Drupal\video\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a VideoEmbeddableProvider attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class VideoEmbeddableProvider extends Plugin {

  /**
   * Constructs a VideoEmbeddableProvider attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A brief description of the plugin.
   * @param array $regular_expressions
   *   (optional) List of regular expressions that match embed codes and URLs
   *   of videos.
   * @param string $mimetype
   *   (optional) A mimetype of the plugin.
   * @param string $stream_wrapper
   *   (optional) A stream_wrapper to use in the plugin.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly array $regular_expressions = [],
    public readonly string $mimetype = '',
    public readonly string $stream_wrapper = '',
    public readonly ?string $deriver = NULL,
  ) {}

}
