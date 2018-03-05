<?php
/**
 * @file
 * Contains \Drupal\exif\ExifInterface
 */

namespace Drupal\exif_custom;


interface ExifCustomInterface {
  function getMetadataFields($arCckFields = array());

  function readMetadataTags($file, $enable_sections = TRUE);

  function getFieldKeys();
}
