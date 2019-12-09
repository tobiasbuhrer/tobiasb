<?php

namespace Drupal\image_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Entity\EntityInterface;
use \Drupal\file\FileInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Class GeneratorForm.
 */
class GeneratorForm extends ConfigFormBase
{
    use MessengerTrait;

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
        return 'generator_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('image_import.imageimportsettings');
        //let's get the number of permanent image files
        $query = \Drupal::entityQuery('file');
        $query->condition('status', FILE_STATUS_PERMANENT);
        $query->condition('filemime', 'image/jpeg');
        $fids = $query->execute();
        $msg = '</p><strong>' . $this->t('Number of original images') . ':</strong> ' . count($fids) . '<br />';

        //number of items in queue
        $NumberQueueItems = \Drupal::service('queue')->get('image_import_image_style')->numberOfItems();
        $msg .= '<strong>' . $this->t('Number of items in queue') . ':</strong> ' . $NumberQueueItems . '</p>';

        $form['msg'] = array(
            '#type' => 'item',
            '#markup' => $msg,
        );

        $form['queue_start'] = array(
            '#type' => 'number',
            '#title' => $this
                ->t('Item from which to start adding items'),
            '#step' => 1,
            '#min' => 1,
            '#max' => count($fids),
            '#default_value' => $config->get('queue_start'),
        );

        $form['queue_range'] = [
            '#type' => 'select',
            '#title' => $this->t('Number of items to be added at once'),
            '#options' => ['50' => $this->t('50'), '100' => $this->t('100'), '250' => $this->t('250'), '500' => $this->t('500'), '1000' => $this->t('1000'), '2000' => $this->t('2000'), '5000' => $this->t('5000')],
            '#size' => 1,
            '#default_value' => $config->get('queue_range'),
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
        $start = $form_state->getValue('queue_start');
        $range = $form_state->getValue('queue_range');

        //add things to the queue
        $query = \Drupal::entityQuery('file');
        $query->condition('status', FILE_STATUS_PERMANENT);
        $query->condition('filemime', 'image/jpeg');
        $query->range($start,$range);
        $fids = $query->execute();
        foreach ($fids as $fid) {
            $entity = \Drupal\file\Entity\File::load($fid);

            if ($entity instanceof FileInterface) {
                $image = \Drupal::service('image.factory')->get($entity->getFileUri());
                /** @var \Drupal\Core\Image\Image $image */
                if ($image->isValid()) {
                    $queue = \Drupal::queue('image_import_image_style');
                    $data = ['entity' => $entity];
                    $queue->createItem($data);
                }
            }
        }
        $msg = $this->t('Images added to queue');
        $this->messenger()->addStatus($msg);

        //let's get the total number of permanent image files
        $query = \Drupal::entityQuery('file');
        $query->condition('status', FILE_STATUS_PERMANENT);
        $query->condition('filemime', 'image/jpeg');
        $fids = $query->execute();
        $start = $start + $range;
        if ($start > count($fids)) {
            $start = $fids;
        }

        $this->config('image_import.imageimportsettings')
            ->set('queue_start', $start)
            ->set('queue_range', $range)
            -> save();
    }

}
