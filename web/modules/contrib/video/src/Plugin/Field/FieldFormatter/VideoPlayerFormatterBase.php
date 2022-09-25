<?php

namespace Drupal\video\Plugin\Field\FieldFormatter;

use Drupal\field\FieldConfigInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;

/**
 * Base class for video player file formatters.
 */
abstract class VideoPlayerFormatterBase extends FileFormatterBase {

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesToView(FieldItemListInterface $items, $langcode) {
    return parent::getEntitiesToView($items, $langcode);
  }
}
