<?php

namespace Drupal\imgtonodes\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ImgToNodesForm.
 */
class ImgToNodesForm extends ConfigFormBase implements ContainerInjectionInterface
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
        return 'imgtonodesform';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('imgtonodes.settings');
        $all_nodetypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
        $all_nt = array();

        foreach ($all_nodetypes as $item) {
            $all_nt[$item->id()] = $item->label();
        }

        $form['nodetype'] = array(
            '#type' => 'select',
            '#title' => t('Nodetype'),
            '#options' => $all_nt,
            '#default_value' => $config->get('nodetype'),
            '#description' => t('Select the nodetype into which photos should be imported.'),
        );


        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        //check whether the selected node type has at least one image field
        $entityManager = \Drupal::service('entity_field.manager');
        $fields = $entityManager->getFieldDefinitions('node', $form_state->getValue('nodetype'));
        $bundletypes = array();

        foreach ($fields as $field_name => $field_definition) {
            if (!empty($field_definition->getTargetBundle())) {
                $bundletypes[] = $field_definition->getType();
            }
        }


        if (!(in_array('image',$bundletypes,true))) {
        $form_state->setErrorByName('nodetype', $this->t('The chosen node type doesn\'t have an image field'));
        }

        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $this->config('imgtonodes.settings')
            ->set('nodetype', $form_state->getValue('nodetype'))
                ->save();
        parent::submitForm($form, $form_state);
    }

}
