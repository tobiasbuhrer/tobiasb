<?php

declare(strict_types=1);

namespace Drupal\visitors\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\visitors\VisitorsCounterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements visitor counter token hooks.
 */
class TokenHooks implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The visitors counter service.
   *
   * @var \Drupal\visitors\VisitorsCounterInterface
   */
  protected $counter;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a TokenHooks object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\visitors\VisitorsCounterInterface $counter
   *   The visitors counter service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Token $token, VisitorsCounterInterface $counter, DateFormatterInterface $date_formatter) {
    $this->configFactory = $config_factory;
    $this->token         = $token;
    $this->counter       = $counter;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('config.factory'),
      $container->get('token'),
      $container->get('visitors.counter'),
      $container->get('date.formatter'),
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo() {
    $entity['total-count'] = [
      'name' => $this->t("Number of views"),
      'description' => $this->t("The number of visitors who have read the node."),
    ];
    $entity['day-count'] = [
      'name' => $this->t("Views today"),
      'description' => $this->t("The number of visitors who have read the node today."),
    ];
    $entity['last-view'] = [
      'name' => $this->t("Last view"),
      'description' => $this->t("The date on which a visitor last read the node."),
      'type' => 'date',
    ];
    $token = [
      'tokens' => [],
    ];
    $entity_types = $this->configFactory->get('visitors.config')->get('counter.entity_types') ?? [];
    foreach ($entity_types as $entity_type) {
      $token['tokens'][$entity_type] = $entity;
    }
    return $token;
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $token_service = $this->token;
    $entity_types = $this->configFactory->get('visitors.config')->get('counter.entity_types') ?? [];
    $replacements = [];
    if (!in_array($type, $entity_types) || empty($data[$type])) {
      return $replacements;
    }
    $entity = $data[$type];
    $entity_id = (int) ($entity->id() ?? 0);
    $entity_view = $this->counter->fetchView($type, $entity_id);
    foreach ($tokens as $name => $original) {
      if ($name == 'total-count') {
        $replacements[$original] = $entity_view ? $entity_view->getTotalCount() : 0;
      }
      elseif ($name == 'day-count') {
        $replacements[$original] = $entity_view ? $entity_view->getDayCount() : 0;
      }
      elseif ($name == 'last-view') {
        $replacements[$original] = $entity_view ? $this->dateFormatter->format($entity_view->getTimestamp()) : $this->t('never');
      }
    }
    if ($created_tokens = $token_service->findWithPrefix($tokens, 'last-view')) {
      $replacements += $token_service->generate('date', $created_tokens, [
        'date' => $entity_view ? $entity_view->getTimestamp() : 0,
      ], $options, $bubbleable_metadata);
    }
    return $replacements;
  }

}
