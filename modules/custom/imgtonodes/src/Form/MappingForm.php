<?php

namespace Drupal\imgtonodes\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MappingForm.
 */
class MappingForm extends ConfigFormBase
{

    /**
     * The entity manager.
     *
     * @var \Drupal\Core\Entity\EntityManagerInterface
     */
    protected $entityTypeManager;

    /**
     * Constructs the ExifCustomSettingsForm object.
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
            'imgtonodes.settings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'mapping_form';
    }

    private function setExifIPTCvalue()
    {
        $metadata = array();
        return $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('imgtonodes.settings');
        $nodetype = $config->get('nodetype');

        //get all the fields of the node type
        $entityManager = \Drupal::service('entity_field.manager');
        $fields = $entityManager->getFieldDefinitions('node', $nodetype);
        $fieldtypes = array();
        $fieldnames = array();

        foreach ($fields as $field_name => $field_definition) {
            if (!empty($field_definition->getTargetBundle())) {
                $fieldtypes[] = $field_definition->getType();
                $fieldnames[] = $field_name;
            }
        }

        for ($x = 0; $x <= count($fieldnames)-1; $x++) {
            $form['display']['markup'][$x] = array(
                '#type' => 'markup',
                '#markup' => $fieldnames[$x] . '(' . $fieldtypes[$x] . ')',
            );
        }

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitForm($form, $form_state);

        $this->config('imgtonodes.settings')
            ->save();
    }

}
