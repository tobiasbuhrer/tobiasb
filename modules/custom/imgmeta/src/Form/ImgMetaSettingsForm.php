<?php

namespace Drupal\imgmeta\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class ImgMetaSettingsForm.
 */
class ImgMetaSettingsForm extends ConfigFormBase implements ContainerInjectionInterface
{
    /**
     * The entity manager.
     *
     * @var \Drupal\Core\Entity\EntityManagerInterface
     */
    protected $entityTypeManager;

    /**
     * Constructs the ImgMetaSettingsForm object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity manager.
     */
    public function __construct(EntityTypeManagerInterface $entity_type_manager)
    {
        $this->entityTypeManager = $entity_type_manager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('entity.manager')
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [
            'imgmeta.imgmetasettings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'imgmeta_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('imgmeta.imgmetasettings');

        $all_nodetypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
        $all_nt = array();
        foreach ($all_nodetypes as $item) {
            $all_nt[$item->id()] = $item->label();
        }

        $form['node_types'] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('Node types'),
            '#description' => $this->t('Select nodetypes which should be checked for IPTC &amp; EXIF data.'),
            '#options' => $all_nt,
            '#default_value' => $config->get('node_types', array()),
        ];
        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        //we check whether each of the selected node types has at least one image field
        $selected_node_types = array_filter($form_state->getValue('node_types'));
        $all_nodetypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
        
        $error_types = array();
        $entityManager = \Drupal::service('entity_field.manager');
        foreach ($selected_node_types as $node_type => $value ) {
            $fields = $entityManager->getFieldDefinitions('node', $node_type);
            $fieldtypes = array();
            foreach ($fields as $field) {
                $fieldtypes[] = $field->getType();
            }

            if (!(in_array('image', $fieldtypes))) {
                $error_types[] = $all_nodetypes[$node_type]->label();
            }
        }
        if (count($error_types) > 0) {
            $form_state->setErrorByName('node_types', $this->t('The following node types do not have an image field and can not be selected: ' . implode(", ", $error_types)));
        }
        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitForm($form, $form_state);

        $this->config('imgmeta.imgmetasettings')
            ->set('node_types', $form_state->getValue('node_types'))
            ->save();
    }

}
