<?php

namespace Drupal\mycsv;


use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Class MycsvBatchExport.
 *
 * @package Drupal\mycsv
 */
class MycsvBatchExport {

  use MessengerTrait;


  /**
   * The batch object.
   *
   * @var array
   */
  private $batch;


  /**
   * The file path to csv file.
   *
   * @var string
   */
  private $filePath;

  /**
   * Constructs a new MycsvBatchExport object.
   *
   * @param string $plugin_id
   *   The plugin_id for mycsv plugin.
   * @param \Drupal\mycsv\MycsvExportPluginInterface $mycsv_plugin
   *   The mycsv plugin.
   * @param bool $print_header_line
   *   Is print header line
   * @param string $delimiter
   *    The delimiter of one batch operation
   * @param int $chunk_size
   *    The chunk size of one batch operation
   * @param string $batch_name
   *    The batch name
   */
  public function __construct($plugin_id, MycsvExportPluginInterface $mycsv_plugin, $print_header_line = TRUE, $delimiter = ';', $chunk_size = 20, $batch_name = 'CSV export') {

    $filename = $plugin_id . '.csv';
    $conf_path = \Drupal::service('site.path');
    $directory = $conf_path . '/files/mycsv/export';
    \Drupal::service('file_system')
      ->prepareDirectory($directory, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $file_path = $directory . '/' . $filename;
    $this->filePath = $file_path;

    $handle = fopen($file_path, 'w');
    if ($print_header_line) {
      $data = $mycsv_plugin->getHeaderData();
      if (!empty($data)) {
        fputcsv($handle, $data, $delimiter, '"');
      }
    }
    fclose($handle);

    $this->batch = [
      'title' => $batch_name,
      'finished' => [$this, 'finished'],
      'file' => drupal_get_path('module', 'mycsv') . '/src/MycsvBatchExport.php',
    ];

    $ids = $mycsv_plugin->getIds();
    $chunks = array_chunk($ids, $chunk_size);
    foreach ($chunks as $data) {
      $this->batch['operations'][] = [
        [get_class($this), 'processBatchOperation'],
        [get_class($mycsv_plugin), $file_path, $delimiter, $data],
      ];
    }

  }


  /**
   * Process batch operation.
   *
   * @param string $mycsv_plugin_class_name
   *   The mycsv plugin class name.
   * @param string $file_path
   *   The file path for export.
   * @param string $delimiter
   *   The delimiter for csv.
   * @param array $ids
   *   The ids.
   * @param array $context
   *   The batch context information.
   */
  public static function processBatchOperation($mycsv_plugin_class_name, $file_path, $delimiter, array $ids, array &$context) {
    $handle = fopen($file_path, 'a');
    foreach ($ids as $id) {
      $context_message = '';
      $data = $mycsv_plugin_class_name::getData($id, $context_message);
      // Если дата одновымерный масив, деламем его многовымерным
      $data = array_values($data);
      if (!is_array($data[0])) {
        $data[] = $data;
      }
      foreach ($data as $data_row) {
        fputcsv($handle, $data_row, $delimiter, '"');
      }
      $context['message'] = $context_message;
      $context['results'][] = $id;
    }
    fclose($handle);
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
      $message = \Drupal::translation()
        ->formatPlural(count($results), 'One post processed.', '@count posts processed.');
    }
    else {
      $message = t('Finished with an error.');
    }
    $this->messenger()->addMessage($message);

    $message = '<h2><a href="/' . $this->filePath . '?t=' . time() . '">Скачать</a></h2>';
    $message = \Drupal\Core\Render\Markup::create($message);
    $this->messenger()->addMessage($message);
  }


}
