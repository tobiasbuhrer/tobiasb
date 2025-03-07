<?php

declare(strict_types=1);

namespace Drupal\image_effects\Component;

/**
 * Image analysis helper methods for GD.
 */
abstract class GdImageAnalysis {

  /**
   * Calculates the mean pixel intensity.
   *
   * @param \GDImage $image
   *   A GD image.
   *
   * @return float
   *   The mean pixel intensity value.
   */
  public static function mean(\GDImage $image): float {
    $mean = 0;
    $size = imagesx($image) * imagesy($image) * 3;
    for ($i = 0; $i < imagesx($image); $i++) {
      for ($j = 0; $j < imagesy($image); $j++) {
        $rgb = imagecolorat($image, $i, $j);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $mean += $r / $size;
        $mean += $g / $size;
        $mean += $b / $size;
      }
    }
    return $mean;
  }

  /**
   * Generates a GD image calculating the difference of two images.
   *
   * The images must have the same dimensions.
   *
   * @param \GDImage $image1
   *   A GD image.
   * @param \GDImage $image2
   *   A GD image.
   *
   * @return \GDImage|null
   *   A GD image, with the subtracted image, or NULL if the dimensions of the
   *   two images differ.
   */
  public static function difference(\GDImage $image1, \GDImage $image2): ?\GDImage {
    if (imagesx($image1) !== imagesx($image2) || imagesy($image1) !== imagesy($image2)) {
      return NULL;
    }
    $difference = imagecreatetruecolor(imagesx($image1), imagesy($image1));
    for ($i = 0; $i < imagesx($image1); $i++) {
      for ($j = 0; $j < imagesy($image1); $j++) {
        $rgb1 = imagecolorat($image1, $i, $j);
        $r1 = ($rgb1 >> 16) & 0xFF;
        $g1 = ($rgb1 >> 8) & 0xFF;
        $b1 = $rgb1 & 0xFF;

        $rgb2 = imagecolorat($image2, $i, $j);
        $r2 = ($rgb2 >> 16) & 0xFF;
        $g2 = ($rgb2 >> 8) & 0xFF;
        $b2 = $rgb2 & 0xFF;

        imagesetpixel($difference, $i, $j, imagecolorallocate(
          $difference,
          abs($r2 - $r1),
          abs($g2 - $g1),
          abs($b2 - $b1)
        ));
      }
    }
    return $difference;
  }

  /**
   * Computes the histogram of an image.
   *
   * @param \GDImage $img
   *   A GD image.
   *
   * @return array
   *   The image histogram as an array.
   */
  public static function histogram(\GDImage $img): array {
    $histogram = array_fill(0, 768, 0);
    for ($i = 0; $i < imagesx($img); $i++) {
      for ($j = 0; $j < imagesy($img); $j++) {
        $rgb = imagecolorat($img, $i, $j);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $histogram[$r]++;
        $histogram[$g + 256]++;
        $histogram[$b + 512]++;
      }
    }
    return $histogram;
  }

  /**
   * Computes the entropy of an image, defined as -sum(p.*log2(p)).
   *
   * @param \GDImage $img
   *   A GD image.
   *
   * @return float
   *   The entropy of the image.
   */
  public static function entropy(\GDImage $img): float {
    $histogram = static::histogram($img);
    $histogram_size = array_sum($histogram);
    $entropy = 0;
    foreach ($histogram as $p) {
      if ($p == 0) {
        continue;
      }
      $p = $p / $histogram_size;
      $entropy += $p * log($p, 2);
    }
    return $entropy * -1;
  }

}
