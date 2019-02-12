<?php

namespace Drupal\mycsv;


abstract class MycsvImportPluginBase extends MycsvPluginBase implements MycsvImportPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function processData(array $data, &$context_message = '') {
    
  }

}