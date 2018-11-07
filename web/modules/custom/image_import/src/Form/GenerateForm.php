<?php
namespace Drupal\image_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Entity\EntityInterface;
use \Drupal\file\FileInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Class GenerateForm.
 */
class GenerateForm extends FormBase
{
    use MessengerTrait;

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'generate_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        //let's get the number of permanent image files
        $query = \Drupal::entityQuery('file');
        $query->condition('status', FILE_STATUS_PERMANENT);
        $query->condition('filemime', 'image/jpeg');

        //for testing
        //$query->range(0,30);

        $fids = $query->execute();
        $msg = '</p><strong>' . $this->t('Number of original images') . ':</strong> ' . count($fids) . '</p>';

        $form['msg'] = array(
            '#type' => 'item',
            '#markup' => $msg,
        );
        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Adding image generation to queue')
        ];
        return $form;

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
        //let's get the number of permanent image files
        $query = \Drupal::entityQuery('file');
        $query->condition('status', FILE_STATUS_PERMANENT);
        $query->condition('filemime', 'image/jpeg');

        //for testing
        //$query->range(0,30);

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
    }

}
