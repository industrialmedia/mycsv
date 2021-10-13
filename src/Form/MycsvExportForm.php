<?php

namespace Drupal\mycsv\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\mycsv\MycsvBatchExport;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mycsv\MycsvPluginManager;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Url;

/**
 * Class ExportForm.
 *
 * @package Drupal\mycsv\Form
 */
class MycsvExportForm extends ConfigFormBase implements ContainerInjectionInterface {

  /**
   * The mycsv plugin manager.
   *
   * @var \Drupal\mycsv\MycsvPluginManager
   */
  protected $mycsvPluginManager;

  /**
   * The file usage.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;


  /**
   * Constructs
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\mycsv\MycsvPluginManager $mycsv_plugin_manager
   *   The mycsv plugin manager
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file usage
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   *
   */
  public function __construct(ConfigFactoryInterface $config_factory, MycsvPluginManager $mycsv_plugin_manager, FileUsageInterface $file_usage, DateFormatterInterface $date_formatter) {
    parent::__construct($config_factory);
    $this->mycsvPluginManager = $mycsv_plugin_manager;
    $this->fileUsage = $file_usage;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /* @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $container->get('config.factory');
    /* @var \Drupal\mycsv\MycsvPluginManager $mycsv_plugin_manager */
    $mycsv_plugin_manager = $container->get('plugin.manager.mycsv.export');
    /* @var \Drupal\file\FileUsage\FileUsageInterface $file_usage */
    $file_usage = $container->get('file.usage');
    /* @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = $container->get('date.formatter');
    return new static(
      $config_factory,
      $mycsv_plugin_manager,
      $file_usage,
      $date_formatter
    );
  }


  /**
   * {@inheritdoc}
   */
  protected function getPluginList() {
    $definitions = $this->mycsvPluginManager->getDefinitions();
    $plugin_list = [];
    foreach ($definitions as $plugin_id => $plugin) {
      $plugin_list[$plugin_id] = $plugin['label'];
    }
    return $plugin_list;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['mycsv.export'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mycsv_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('mycsv.export');
    $export_plugin = !empty($_GET['export_plugin']) ? $_GET['export_plugin'] : '';
    $plugins = $this->getPluginList();
    $export_plugin = array_key_exists($export_plugin, $plugins) ? $export_plugin : '';

    $form['export_plugin'] = [
      '#title' => $this->t('Select content type to export'),
      '#type' => 'select',
      '#options' => $plugins,
      '#empty_option' => '- Select -',
      '#required' => TRUE,
      '#default_value' => $export_plugin ? $export_plugin : NULL,
    ];

    $form['actions']['start_export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start export'),
      '#submit' => ['::submitForm', '::startExport'],
      '#weight' => 100,
      '#name' => 'start_export',
    ];


    $form['additional_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Additional settings'),
    ];
    $form['additional_settings']['print_header_line'] = [
      '#type' => 'checkbox',
      '#title' => t('Print header line'),
      '#default_value' => $config->get('print_header_line'),
    ];
    $form['additional_settings']['delimiter'] = [
      '#type' => 'textfield',
      '#title' => t('Delimiter'),
      '#default_value' => $config->get('delimiter'),
      '#required' => TRUE,
      '#size' => 3,
    ];
    $form['additional_settings']['chunk_size'] = [
      '#type' => 'number',
      '#title' => t('Chunk size'),
      '#default_value' => $config->get('chunk_size'),
      '#required' => TRUE,
      '#min' => 1,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($form_state->getTriggeringElement()['#name'] == 'start_export') {
      if (!$form_state->getValue('export_plugin')) {
        $form_state->setErrorByName('export_plugin', $this->t('You must select content type to export.'));
      }
      if ($form_state->getValue('chunk_size') < 1) {
        $form_state->setErrorByName('chunk_size', $this->t('Chunk size must be greater or equal 1.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('mycsv.export');
    $form_state->setRebuild();
    $config->set('print_header_line', $form_state->getValue('print_header_line'));
    $config->set('delimiter', $form_state->getValue('delimiter'));
    $config->set('chunk_size', $form_state->getValue('chunk_size'));
    $config->save();
  }

  /**
   * Метод для начала экспорта в файл.
   */
  public function startExport(array &$form, FormStateInterface $form_state) {
    $config = $this->config('mycsv.export');
    $print_header_line = $config->get('print_header_line');
    $delimiter = $config->get('delimiter');
    $chunk_size = $config->get('chunk_size');
    $plugin_id = $form_state->getValue('export_plugin');
    /* @var \Drupal\mycsv\MycsvExportPluginInterface $mycsvPlugin */
    $mycsvPlugin = $this->mycsvPluginManager->createInstance($plugin_id);
    $export = new MycsvBatchExport($plugin_id, $mycsvPlugin, $print_header_line, $delimiter, $chunk_size);
    $export->setBatch();
  }


}