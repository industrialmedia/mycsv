<?php

namespace Drupal\mycsv;


interface MycsvImportPluginInterface extends MycsvPluginInterface {



  /**
   * Process data row from csv file.
   *
   * @param array $data
   *   The data row from csv file.
   * @param string $context_message
   *   The context message.
   */
  public static function processData(array $data, &$context_message = '');
  

}