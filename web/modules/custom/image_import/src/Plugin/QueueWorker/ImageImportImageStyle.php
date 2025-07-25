<?php
namespace Drupal\image_import\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executes image style creation  queue tasks.
 *
 * @QueueWorker(
 *   id = "image_import_image_style",
 *   title = @Translation("Generate image styles"),
 *   cron = {"time" = 1500 }
 * )
 */
class ImageImportImageStyle extends QueueWorkerBase implements ContainerFactoryPluginInterface {

    /**
     * The image style entity storage.
     *
     * @var \Drupal\image\ImageStyleStorageInterface
     */
    protected $imageStyleStorage;

    /**
     * Constructs a new LocaleTranslation object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param array $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
     *   The image style storage.
     */
    public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityStorageInterface $image_style_storage) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->imageStyleStorage = $image_style_storage;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('entity_type.manager')->getStorage('image_style')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function processItem($data) {
        /** @var \Drupal\file\Entity\File $entity */
        $entity = $data['entity'];
        $styles = $this->imageStyleStorage->loadMultiple();
        //old
        //$image_uri = $entity->getFileUri();
        //maybe try:
        $image_uri =\Drupal::service('file_system')->realpath($entity->getFileUri());
                
        /** @var \Drupal\image\Entity\ImageStyle $style */
        foreach ($styles as $style) {
            $destination = $style->buildUri($image_uri);
            $style->createDerivative($image_uri, $destination);
        }
    }

}
