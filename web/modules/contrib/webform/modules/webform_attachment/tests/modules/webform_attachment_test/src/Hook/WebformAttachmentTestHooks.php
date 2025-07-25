<?php

namespace Drupal\webform_attachment_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_attachment_test.
 */
class WebformAttachmentTestHooks {

  /**
   * Implements hook_webform_load().
   */
  #[Hook('webform_load')]
  public function webformLoad(array $entities) {
    if (!isset($entities['test_attachment_url'])) {
      return;
    }
    global $base_url;
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $entities['test_attachment_url'];
    $elements = $webform->getElementsDecodedAndFlattened();
    // Set absolute URL.
    $element_keys = ['webform_attachment_url', 'webform_attachment_url_download'];
    foreach ($element_keys as $element_key) {
      if (isset($elements[$element_key])) {
        $element = $elements[$element_key];
        if (empty($element['#url'])) {
          $element['#url'] = $base_url . '/core/MAINTAINERS.txt';
          $webform->setElementProperties($element_key, $element);
        }
      }
    }
    // Set root relative URL.
    if (isset($elements['webform_attachment_path'])) {
      $element = $elements['webform_attachment_path'];
      $element['#url'] = base_path() . 'core/misc/druplicon.png';
      $webform->setElementProperties('webform_attachment_path', $element);
    }
  }

}
