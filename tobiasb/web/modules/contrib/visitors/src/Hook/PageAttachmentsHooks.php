<?php

declare(strict_types=1);

namespace Drupal\visitors\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Implements hook_page_attachments().
 */
class PageAttachmentsHooks {

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public static function pageAttachments(array &$page) {
    \Drupal::service('visitors.page_attachments')->pageAttachments($page);
  }

}
