<?php

namespace Drupal\url_checker\Batch;

use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class UrlCheckerBatch.
 *
 * @package Drupal\url_checker
 */
class UrlCheckerBatch {

  /**
   * Common batch processing callback for all operations.
   *
   * @param string $url
   *   The url string we are checking.
   * @param object &$context
   *   The batch context object.
   */
  public static function batchProcess($url, &$context) {
    // Show message.
    $message = t('Now checking %url', ['%url' => $url]);
    $context['message'] = '<h2>' . $message . '</h2>';

    // Create our temp file to download to.
    if (empty($context["results"]['final'])) {
      $timestamp = \Drupal::time()->getRequestTime();
      $filename = 'Url-Checker-' . $timestamp . '.csv';
      $directory = "public://export/";
      file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
      $destination = $directory . $filename;
      $file = file_save_data('', $destination, FILE_EXISTS_REPLACE);
      if (!$file) {
        return;
      }
      $file->setTemporary();
      $file->save();

      $context['results']['file'] = $file->getFileUri();

      $fopen = fopen($context['results']['file'], 'wb');
      $headers = ['url', 'title', 'status code', 'redirect count'];
      fputcsv($fopen, $headers);
      fclose($fopen);
    }

    list($body, $status) = self::curlUrl($url);
    if (substr($status["http_code"], 0, 1) === '2') {
      $title = self::getTitle($body);
    }

    $row = [
      $url,
      $title ?? '',
      $status["http_code"],
      $status["redirect_count"],
    ];

    $fopen = fopen($context['results']['file'], 'ab');
    fputcsv($fopen, $row);
    fclose($fopen);

    // Set the result.
    $context['results']['final'][] = $row;
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success && isset($results['file']) && file_exists($results['file'])) {

      $headers = \Drupal::moduleHandler()->invokeAll('file_download', [$results['file']]);
      if (!empty($headers) && !in_array(-1, $headers)) {

        // Create a web server accessible URL for the private file.
        // Permissions for accessing this URL will be inherited from the View
        // display's configuration.
        $url = file_create_url($results['file']);

        \Drupal::messenger()->addStatus(t('Export complete. Download the file <a href=":download_url">here</a>.', [':download_url' => $url]));
      }
    }
    else {
      $error_operation = reset($operations);
      \Drupal::messenger()->addError(t('An error occurred while processing @operation with arguments : @args', [
        '@operation' => $error_operation[0],
        '@args' => print_r($error_operation[0], TRUE)
      ]));
    }
  }

  /**
   * Returns datas via curl.
   *
   * @param string $url
   *   The url we are checking.
   *
   * @return array
   *   The status infos.
   */
  public static function curlUrl($url) {
    $agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($curl, CURLOPT_ENCODING, '');
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($curl, CURLOPT_TIMEOUT_MS, 5000);
    curl_setopt($curl, CURLOPT_USERAGENT, $agent);
    $output = curl_exec($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    return [$output, $info];
  }

  /**
   * Get the title of the page.
   *
   * @param string $body
   *   The body we are checking.
   *
   * @return string
   *   The title of the page or just the url without the params.
   */
  public static function getTitle($body) {
    // Supports line breaks inside <title>.
    $str = trim(preg_replace('/\s+/', ' ', $body));
    // Check for the title.
    preg_match("/<title[^>]*>(.*?)<\/title>/is", $str, $title);
    return isset($title[1]) && !empty($title[1]) ? mb_convert_encoding($title[1], 'UTF-8', 'UTF-8') : '';
  }

}
