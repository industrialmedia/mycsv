<?php

namespace Drupal\mycsv;


abstract class MycsvExportPluginBase extends MycsvPluginBase implements MycsvExportPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getHeaderData() {
    return [];
  }


  /**
   * {@inheritdoc}
   */
  public static function getData($id, &$context_message = '') {
    return [];
  }




}