<?php
/**
 * @file
 * Contains \Drupal\exif\ExifCustomFactory
 */

namespace Drupal\exif_custom;

use Drupal;

class ExifCustomFactory {


  public static function getExtractionSolutions() {
    return array(
      "simple_exiftool" => "exiftool",
      "php_extensions"  => "php extensions"
    );
  }

  public static function getExifInterface() {
    $config = Drupal::configFactory()->get('exif_custom.settings');
    $extractionSolution = $config->get('extraction_solution');
    $useExifToolSimple  = $extractionSolution == "simple_exiftool";
    if (isset($useExifToolSimple) && $useExifToolSimple && SimpleExifCustomToolFacade::checkConfiguration()) {
      return SimpleExifCustomToolFacade::getInstance();
    } else {
      //default case for now (same behavior as previous versions)
      return ExifCustomPHPExtension::getInstance();
    }
  }

}
