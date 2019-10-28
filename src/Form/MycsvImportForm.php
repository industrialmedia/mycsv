<?php

namespace Drupal\mycsv\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\mycsv\MycsvBatchImport;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mycsv\MycsvPluginManager;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Url;

/**
 * Class ImportForm.
 *
 * @package Drupal\mycsv\Form
 */
class MycsvImportForm extends ConfigFormBase implements ContainerInjectionInterface {

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
    $mycsv_plugin_manager = $container->get('plugin.manager.mycsv.import');
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
    return ['mycsv.import'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mycsv_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('mycsv.import');
    $import_plugin = !empty($_GET['import_plugin']) ? $_GET['import_plugin'] : '';
    $plugins = $this->getPluginList();
    $import_plugin = array_key_exists($import_plugin, $plugins) ? $import_plugin : '';

    $form['import_plugin'] = [
      '#title' => $this->t('Select content type to import'),
      '#type' => 'select',
      '#options' => $plugins,
      '#empty_option' => '- Select -',
      '#required' => TRUE,
      '#default_value' => $import_plugin ? $import_plugin : NULL,
      '#ajax' => [
        'callback' => '::ajaxSubmitCallback',
        'progress' => [
          'type' => 'throbber',
        ],
      ],
    ];

    # Если выбран плагин
    if (!empty($import_plugin)) {
      $fid = $config->get('fids.' . $import_plugin);
      $form['file'] = [
        '#title' => $this->t('CSV file'),
        '#type' => 'managed_file',
        '#upload_location' => 'public://',
        '#default_value' => $fid ? [$fid] : NULL,
        '#upload_validators' => array(
          'file_validate_extensions' => array('csv'),
        ),
        '#required' => TRUE,
      ];
      if ($fid) {
        $file = File::load($fid);
        if ($file) {
          $created = $file->get('created')->first()->getValue()['value'];
          $created = $this->dateFormatter->format($created, 'medium');
          $form['file_information'] = [
            '#markup' => $this->t('This file was uploaded at @created.', ['@created' => $created]),
          ];
        }
      }
      $form['actions']['start_import'] = [
        '#type' => 'submit',
        '#value' => $this->t('Start import'),
        '#submit' => ['::submitForm', '::startImport'],
        '#weight' => 100,
        '#name' => 'start_import',
      ];
    }


    $form['additional_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Additional settings'),
    ];
    $form['additional_settings']['skip_first_line'] = [
      '#type' => 'checkbox',
      '#title' => t('Skip first line'),
      '#default_value' => $config->get('skip_first_line'),
      '#description' => t('If file contain titles, this checkbox help to skip first line.'),
    ];
    $form['additional_settings']['delimiter'] = [
      '#type' => 'textfield',
      '#title' => t('Delimiter'),
      '#default_value' => $config->get('delimiter'),
      '#required' => TRUE,
      '#size' => 3,
    ];
    $form['additional_settings']['enclosure'] = [
      '#type' => 'textfield',
      '#title' => t('Enclosure'),
      '#default_value' => $config->get('enclosure'),
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
    if ($form_state->getTriggeringElement()['#name'] == 'start_import') {
      if (!$form_state->getValue('import_plugin')) {
        $form_state->setErrorByName('import_plugin', $this->t('You must select content type to import.'));
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
    $config = $this->config('mycsv.import');
    $form_state->setRebuild();
    $import_plugin = $form_state->getValue('import_plugin');
    if (!empty($import_plugin)) {
      $fid_old = $config->get('fids.' . $import_plugin);
      $fid_form = $form_state->getValue('file')[0];
      if (empty($fid_old) || $fid_old != $fid_form) {
        if (!empty($fid_old) && $previous_file = File::load($fid_old)) {
          $this->fileUsage->delete($previous_file, 'mycsv', 'config_form', $previous_file->id());
        }
        $new_file = File::load($fid_form);
        $new_file->save();
        $this->fileUsage->add($new_file, 'mycsv', 'config_form', $new_file->id());
        $config->set('fids.' . $import_plugin, $fid_form)
          ->set('creation', time());
      }
    }
    $config->set('skip_first_line', $form_state->getValue('skip_first_line'));
    $config->set('delimiter', $form_state->getValue('delimiter'));
    $config->set('enclosure', $form_state->getValue('enclosure'));
    $config->set('chunk_size', $form_state->getValue('chunk_size'));
    $config->save();
  }

  /**
   * Метод для начала импорта из файла.
   */
  public function startImport(array &$form, FormStateInterface $form_state) {
    $config = $this->config('mycsv.import');
    $import_plugin = $form_state->getValue('import_plugin');
    $fid = $config->get('fids.' . $import_plugin);
    $skip_first_line = $config->get('skip_first_line');
    $delimiter = $config->get('delimiter');
    $enclosure = $config->get('enclosure');
    $chunk_size = $config->get('chunk_size');
    $plugin_id = $form_state->getValue('import_plugin');
    $plugin_definition = $this->mycsvPluginManager->getDefinition($plugin_id);
    $import = new MycsvBatchImport($plugin_id, $plugin_definition, $fid, $skip_first_line, $delimiter, $enclosure, $chunk_size);
    $import->setBatch();
  }


  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state) {
    $import_plugin = $form_state->getValue('import_plugin');
    $options['query'] = $_GET;
    unset($options['query']['ajax_form']);
    unset($options['query']['_wrapper_format']);
    $options['query']['import_plugin'] = $import_plugin;
    $url = Url::fromRoute('mycsv.admin.import');
    $url->setOptions($options);
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand($url->toString()));
    return $response;
  }

}
