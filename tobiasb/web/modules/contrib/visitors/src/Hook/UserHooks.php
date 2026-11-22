<?php

declare(strict_types=1);

namespace Drupal\visitors\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserDataInterface;
use Drupal\visitors\VisitorsVisibilityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements hook_form_user_form_alter().
 */
class UserHooks implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Constructs a UserHooks object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, UserDataInterface $user_data) {
    $this->configFactory = $config_factory;
    $this->userData      = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('config.factory'),
      $container->get('user.data'),
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Allow users to decide if tracking code will be added to pages or not.
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(&$form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('visitors.config');
    $visibility_users = $config->get('visibility.user_account_mode');
    if ($visibility_users == VisitorsVisibilityInterface::USER_NO_PERSONALIZATION) {
      return;
    }
    /** @var \Drupal\user\AccountForm $user_form */
    $user_form = $form_state->getFormObject();
    /** @var \Drupal\user\UserInterface $account */
    $account = $user_form->getEntity();
    if (!$account->hasPermission('opt-out of visitors tracking')) {
      return;
    }
    $account_data_visitors = $this->userData->get('visitors', $account->id());
    $form['visitors'] = [
      '#type' => 'details',
      '#title' => $this->t('Visitors settings'),
      '#weight' => 3,
      '#open' => TRUE,
    ];
    $description = '';
    switch ($visibility_users) {
      case VisitorsVisibilityInterface::USER_OPT_OUT:
        $description = $this->t('Users are tracked by default, but you are able to opt out.');
        break;

      case VisitorsVisibilityInterface::USER_OPT_IN:
        $description = $this->t('Users are <em>not</em> tracked by default, but you are able to opt in.');
        break;
    }
    $default_value = $account_data_visitors['user_account_users'] ?? $visibility_users;
    $form['visitors']['user_account_users'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable user tracking'),
      '#description' => $description,
      '#default_value' => $default_value,
    ];
    // Custom submit handler.
    $form['actions']['submit']['#submit'][] = 'visitors_user_profile_form_submit';
  }

}
