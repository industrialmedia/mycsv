<?php

namespace Drupal\mycsv;

use Drupal\Component\Plugin\PluginInspectionInterface;



interface MycsvPluginInterface extends PluginInspectionInterface {

  /**
   * {@inheritdoc}
   */
  public function getId();




  /**
   * Gets plugin label.
   *
   * @return string
   *   The plugin label.
   */
  public function getLabel();


  /**
   * Gets plugin status.
   *
   * @return bool
   *   The plugin status enabled.
   */
  public function getEnabled();


  /**
   * Gets plugin weight.
   *
   * @return int
   *   The plugin weight.
   */
  public function getWeight();





}