<?php

namespace Drupal\image_styles_creator\Form;

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
        $config = $this->config('image_styles_creator.imagestylescreatorsettings');
        $styles = $config->get('image_styles');
        $msg = '<div class="result_message"><strong>' . $this->t('The following styles will be created') . ':</strong><br />';
        foreach ($styles as $key=>$value) {
            if ($value) {
                $msg .= $value . '<br />';
            }
        }


        //let's get the number of permanent image files
        $query = \Drupal::entityQuery('file');
        $query->condition('status', FILE_STATUS_PERMANENT);
        $query->condition('filemime', 'image/jpeg');

        //for testing
        //$query->range(0,30);

        $fids = $query->execute();
        $msg .= '</p><p>&nbsp;</p></p><div><strong>' . $this->t('Number of original images') . ':</strong> ' . count($fids) . '</div><p>&nbsp;</p>';

        $form['msg'] = array(
            '#type' => 'item',
            '#markup' => $msg,
        );
        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Generate image styles - this is going to take time!')
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
        $config = $this->config('image_styles_creator.imagestylescreatorsettings');
        $styles = $config->get('image_styles');
        foreach ($styles as $key=>$value) {
            if ($value) {
                $imagestyles[] = ImageStyle::load($value);
            }
        }

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

                    $image_uri = $entity->getFileUri();
                    /** @var \Drupal\image\Entity\ImageStyle $style */
                    foreach ($imagestyles as $style) {
                        $destination = $style->buildUri($image_uri);
                        $style->createDerivative($image_uri, $destination);
                    }
                }
            }
        }
        $msg = $this->t('All done');
        $this->messenger()->addStatus($msg);
    }

}
