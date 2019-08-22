<?php

namespace Drupal\url_checker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class UrlCheckerForm.
 */
class UrlCheckerForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'url_checker_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['get'] = [
      '#type' => 'markup',
      '#markup' => '<h2>' . $this->t('Import your list below') . '</h2>',
    ];

    $form['upload'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check here if you want to upload via file'),
    ];

    $form['urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Urls to upload'),
      '#states' => [
        'visible' => [':input[name="upload"]' => ['checked' => FALSE]],
      ],
    ];

    $form['uploadz'] = [
      '#type' => 'file',
      '#title' => $this->t('File to upload'),
      '#description' => $this->t('Upload a file, allowed extensions: txt'),
      '#states' => [
        'visible' => [':input[name="upload"]' => ['checked' => TRUE]],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate the form here instead of using required.
    // Works a little better for our purposes.
    $upload = $form_state->getValue('upload');
    $urls = $form_state->getValue('urls');

    if (!$upload && empty($urls)) {
      $form_state->setErrorByName('urls', 'You can\'t leave this blank');
    }
    elseif ($upload) {
      $file = file_save_upload('uploadz');
      if (!isset($file)) {
        $form_state->setErrorByName('uploadz', 'Where\'s your file at?');
      }
      else {
        $validators = ['file_validate_extensions' => ['txt']];
        $file = file_save_upload('uploadz', $validators);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Process the URLS.
    $urls = $this->processUrls($form_state);

    foreach ($urls as $url) {
      $operations[] = ['Drupal\url_checker\Batch\UrlCheckerBatch::batchProcess', [$url]];
    }

    // Fail safe in case the batch fails in building the operations.
    if (isset($operations)) {
      // Set the batch to win the stuff.
      $batch = [
        'title' => $this->t('Checking...'),
        'operations' => $operations,
        'init_message' => $this->t('Importing URLs to process.'),
        'finished' => 'Drupal\url_checker\Batch\UrlCheckerBatch::batchFinished',
        'file' => drupal_get_path('module', 'url_checker') . '/src/Batch/UrlCheckerBatch.php'
      ];

      // Engage.
      batch_set($batch);
    }
    else {
      \Drupal::messenger()->addWarning(t('No Valid URLs to Process!'));
    }
  }

  /**
   * Make all the urls happy via text or file.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Obvious form objects.
   *
   * @return mixed
   *   The parsed happiness of urls to win.
   */
  protected function processUrls(FormStateInterface $form_state) {
    // Grab the urls.
    $urls = $form_state->getValue('urls');

    // If a File.
    if (empty($urls)) {
      // Get the file.
      $file = file_save_upload('uploadz');
      $uri = $file[0]->getFileUri();
      $handle = fopen($uri, 'r');
      $urls = [];
      // Better file handling for large filez.
      if ($handle) {
        while (($line = fgets($handle)) !== FALSE) {
          // This is removing any odd stuffs in the file.
          $line_fix = utf8_decode($line);
          $line_fix = str_replace("\0", "", $line_fix);
          $line_fix = str_replace("??", "", $line_fix);
          $line_fix = preg_replace('/\s+/', '', $line_fix);
          $urls[] = $line_fix;
        }
        fclose($handle);

        // Clean up temp files.
        file_unmanaged_delete($uri);
      }
      else {
        \Drupal::messenger()->addError(t('Could not open file!'));
      }
    }
    else {
      // If the text box.
      $urls = preg_split("/\r\n|\n|\r/", $urls);
    }

    // Put the URLs into an array and try to get them in the best
    // format possible for domination.
    $parsed_urls = [];
    foreach ($urls as $url) {
      $url = trim(preg_replace("/\s+/", " ", $url));
      if (filter_var($url, FILTER_VALIDATE_URL)) {
        $parsed_urls[] = $url;
      }
    }
    return $parsed_urls;
  }

}
