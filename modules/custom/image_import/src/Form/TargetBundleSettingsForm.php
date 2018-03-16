<?php

namespace Drupal\image_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class TargetBundleSettingsForm.
 */
class TargetBundleSettingsForm extends ConfigFormBase
{
    /**
     * Constructs the TargetBundleSettingsForm object.
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
            'image_import.imageimportsettings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'target_bundle_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $config = $this->config('image_import.imageimportsettings');
        $all_nodetypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
        $all_nt = array();
        foreach ($all_nodetypes as $item) {
            //lets check whether the node type has at least one image field
            $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $item->id());
            foreach ($fields as $field) {
                if ($field->getType() == 'image') {
                    $all_nt[$item->id()] = $item->label();
                    break;
                }
            }
        }

        $form['contenttype'] = [
            '#type' => 'select',
            '#title' => $this
                ->t('Select element'),
            '#options' => $all_nt,
            '#default_value' => $config->get('contenttype'),
        ];
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

        $this->config('image_import.imageimportsettings')
            ->set('contenttype', $form_state->getValue('contenttype'))
            ->save();
    }

}
