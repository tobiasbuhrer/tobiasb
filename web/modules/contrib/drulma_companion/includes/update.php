<?php

/**
 * @file
 * Update hooks.
 */

/**
 * Install hook event dispatcher 8.x-2.x submodules.
 */
function drulma_companion_update_8101() {
  \Drupal::service('module_installer')->install(['core_event_dispatcher']);
}
