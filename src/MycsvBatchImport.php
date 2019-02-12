<?php

namespace Drupal\mycsv;

use Drupal\file\Entity\File;


/**
 * Class MycsvBatchImport.
 *
 * @package Drupal\mycsv
 */
class MycsvBatchImport {


  /**
   * The batch object.
   *
   * @var array
   */
  private $batch;


  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, $fid, $skip_first_line = FALSE, $delimiter = ';', $enclosure = ',', $chunk_size = 20, $batch_name = 'CSV import') {


    $this->batch = [
      'title' => $batch_name,
      'finished' => [$this, 'finished'],
      'file' => drupal_get_path('module', 'mycsv') . '/src/MycsvBatchImport.php',
    ];

    $items = [];
    $file_uri = File::load($fid)->getFileUri();
    if (($handle = fopen($file_uri, 'r')) !== FALSE) {
      if ($skip_first_line) {
        fgetcsv($handle, 0, $delimiter);
      }
      while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
        $items[] = $data;
      }
      fclose($handle);
    }

    $chunks = array_chunk($items, $chunk_size);
    foreach ($chunks as $data) {
      $this->batch['operations'][] = [
        [get_class($this), 'processBatchOperation'],
        [$plugin_definition['class'], $data]
      ];
    }

  }



  /**
   * Process batch operation.
   *
   * @param string $mycsv_plugin_class_name
   *   The mycsv plugin class name.
   * @param array $data
   *   The data from csv file.
   * @param array $context
   *   The batch context information.
   */
  public static function processBatchOperation($mycsv_plugin_class_name, array $data, array &$context) {
    foreach ($data as $row) {
      $context_message = '';
      $mycsv_plugin_class_name::processData($row, $context_message);
      $context['message'] = $context_message;
      $context['results'][] = '';
    }
  }




  /**
   * Adds a new batch.
   */
  public function setBatch() {
    batch_set($this->batch);
  }



  /**
   * Batch finished callback: display batch statistics.
   *
   * @param bool $success
   *   Indicates whether the batch has completed successfully.
   * @param mixed[] $results
   *   The array of results gathered by the batch processing.
   * @param string[] $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public function finished($success, array $results, array $operations) {
    if ($success) {
      $count = count($results);
      $message = \Drupal::translation()
        ->formatPlural($count, 'One post processed.', '@count posts processed.');
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
  }

}
