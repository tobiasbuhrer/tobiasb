<?php

declare(strict_types=1);

namespace Drupal\video_transcode\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a transcoder plugin attribute object.
 *
 * Plugin Namespace: Plugin\video\Transcoder
 *
 * @see \Drupal\video_transcode\TranscoderManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Transcoder extends Plugin {

  /**
   * Constructs a Transcoder attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label of the transcoder.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A brief description of the transcoder.
   * @param bool $isExternal
   *   (optional) Whether the service is external, or a locally installed
   *   service like Ffmpeg.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly bool $isExternal = FALSE,
    public readonly ?string $deriver = NULL,
  ) {}

}
