<?php

namespace Drupal\imgtonodes\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class ImgtonodesController.
 */
class ImgtonodesController extends ControllerBase {

  /**
   * Import.
   *
   * @return string
   *   Return Hello string.
   */
  public function import() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Implement method: import')
    ];
  }

}
