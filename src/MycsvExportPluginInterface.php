<?php

namespace Drupal\mycsv;


interface MycsvExportPluginInterface extends MycsvPluginInterface {


  /**
   * Get ids to batch operation.
   *
   * @return array
   *   The ids to batch operation.
   */
  public function getIds();



  /**
   * Get header data row to csv file.
   *
   * @return array
   *   The header data row to csv file.
   */
  public function getHeaderData();


  /**
   * Get data row (or array data rows) to csv file.
   *
   * @param $id
   *   The id.
   * @param string $context_message
   *   The context message.
   * @param $context_results
   *   The context results.
   * @return array
   *   The data row (or array data rows) to csv file.
   */
  public static function getData($id, &$context_message = '');




}