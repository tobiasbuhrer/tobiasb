<?php

namespace Drupal\drulma_companion\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\core_event_dispatcher\Event\Theme\ThemesInstalledEvent;
use Drupal\hook_event_dispatcher\HookEventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add container class when drulma or any theme base on drulma is installed.
 */
class AddContainerClass implements EventSubscriberInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Constructs a new AddContainerClass instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   *   The theme handler service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ThemeHandlerInterface $themeHandler
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->themeHandler = $themeHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      HookEventDispatcherInterface::THEMES_INSTALLED => 'addClass',
    ];
  }

  /**
   * Respond to the event.
   */
  public function addClass(ThemesInstalledEvent $event) {
    foreach ($event->getThemeList() as $theme) {
      // The installed theme is drulma or has drulma as a base theme.
      if ($theme === 'drulma' || in_array('drulma', array_keys($this->themeHandler->getTheme($theme)->base_themes ?? []), TRUE)) {
        foreach ([
          'branding',
          'footer',
          'powered',
          'messages',
        ] as $blockId) {
          $block = $this->entityTypeManager->getStorage('block')->load($theme . '_' . $blockId);
          if ($block) {
            $third_party_settings = $block->get('third_party_settings');
            if (!$third_party_settings) {
              $third_party_settings = [
                "block_class" => [
                  "classes" => "container",
                ],
              ];
              $block->set('third_party_settings', $third_party_settings);
              $dependencies = $block->get('dependencies');
              $dependencies['module'][] = 'block_class';
              $block->set('dependencies', $dependencies);
              $block->save();
            }
          }
        }
      }
    }
  }

}
