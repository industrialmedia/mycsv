<?php

namespace Drupal\mycsv\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Annotations for CustomCSVImport plugins.
 *
 * @Annotation
 */
class MycsvExport extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * Label will be used in interface.
   *
   * @var string
   */
  public $label;
  
  /**
   * The plugin status.
   *
   * @var bool
   */
  public $enabled;

  /**
   * The weight of plugin.
   *
   * @var int
   */
  public $weight;

}
