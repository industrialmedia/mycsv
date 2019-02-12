<?php

namespace Drupal\mycsv\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Annotations for CustomCSVImport plugins.
 *
 * @Annotation
 */
class MycsvImport extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * Label will be used in interface.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
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
