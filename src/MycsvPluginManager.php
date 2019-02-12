<?php

namespace Drupal\mycsv;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Symfony\Component\DependencyInjection\Container;

/**
 * Provides an Mycsv plugin manager.
 */
class MycsvPluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct($type, \Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $type_camelized = Container::camelize($type);
    $subdir = 'Plugin/Mycsv/' . $type_camelized;
    $plugin_interface = 'Drupal\mycsv\Mycsv' . $type_camelized . 'PluginInterface';
    $plugin_definition_annotation_name = 'Drupal\mycsv\Annotation\Mycsv' . $type_camelized;
    parent::__construct(
      $subdir,
      $namespaces,
      $module_handler,
      $plugin_interface,
      $plugin_definition_annotation_name
    );
    $this->defaults += [
      'plugin_type' => $type,
      'enabled' => TRUE,
      'weight' => 0,
    ];

    # hook_mycsv_TYPE_info_alter();
    $this->alterInfo('mycsv_' . $type . '_info');
    $this->setCacheBackend($cache_backend, 'mycsv:' . $type);
    $this->factory = new ContainerFactory($this->getDiscovery());
  }

}
