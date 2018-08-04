<?php

namespace fenetikm;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\Loader\ArrayLoader;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event as ScriptEvent;
use Composer\Script\ScriptEvents;

/**
 * Composer plugin that pulls in Drupal modules to the autoloader.
 */
class DrupalAutoloadPlugin implements PluginInterface, EventSubscriberInterface {

  /**
   * Priority that plugin uses to register callbacks.
   *
   * @var int
   */
  const CALLBACK_PRIORITY = 50000;

  /**
   * Composer object.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return array(
      ScriptEvents::PRE_AUTOLOAD_DUMP =>
      array('onInstallUpdateOrDump', self::CALLBACK_PRIORITY),
      ScriptEvents::PRE_INSTALL_CMD =>
      array('onInstallUpdateOrDump', self::CALLBACK_PRIORITY),
      ScriptEvents::PRE_UPDATE_CMD =>
      array('onInstallUpdateOrDump', self::CALLBACK_PRIORITY),
    );
  }

  /**
   * Install / update or dump event handler.
   *
   * @param \Composer\Script\Event $event
   *   The event.
   */
  public function onInstallUpdateOrDump(ScriptEvent $event) {
    $this->mergeAutoload();
  }

  /**
   * Build the namespace array for a modules entry.
   *
   * @param array $namespaces
   *   The current namespaces array to add into.
   * @param string $base_dir
   *   The base directory to build from.
   * @param array $constrain
   *   Modules to constrain to.
   */
  public function buildNamespace(array &$namespaces, $base_dir, array $constrain = []) {
    // Add in trailing slash if missing.
    if ($base_dir[strlen($base_dir) - 1] != '/') {
      $base_dir .= '/';
    }

    $filter_out = ['.', '..'];
    $dir_exists = file_exists($base_dir);
    if (!$dir_exists) {
      return;
    }

    $files = scandir($base_dir);
    if ($files === FALSE) {
      return;
    }

    $module_directories = array_filter($files, function ($item) use ($filter_out, $base_dir, $constrain) {
      // Only insert modules with src directories.
      $has_src = file_exists($base_dir . $item . '/src');

      // Check against specific modules if applicable.
      $is_module = TRUE;
      if (!empty($constrain)) {
        $is_module = in_array($item, $constrain);
      }

      return $has_src &&
        $is_module &&
        !in_array($item, $filter_out) &&
        is_dir($base_dir . $item);
    });

    array_map(function ($directory) use ($base_dir, &$namespaces) {
      $this->addModuleToNamespaces($namespaces, $directory, $base_dir);
    }, $module_directories);
  }

  /**
   * Add module to namespaces.
   *
   * @param array $namespaces
   *   The current namespaces array to add into.
   * @param string $directory
   *   The directory of the module.
   * @param string $base_dir
   *   The base directory to build from.
   */
  public function addModuleToNamespaces(array &$namespaces, $directory, $base_dir) {
    $namespace = 'Drupal\\' . $directory . '\\';
    $namespaces[$namespace] = $base_dir . $directory . '/src';
  }

  /**
   * Build the classmap array.
   */
  public function buildClassmap() {
    $extra = $this->composer->getPackage()->getExtra();
    $classmap = isset($extra['autoload-drupal']['classmap']) ? $extra['autoload-drupal']['classmap'] : [];
    return $classmap;
  }

  /**
   * Build the namespaces array.
   */
  public function buildNamespaces() {
    $extra = $this->composer->getPackage()->getExtra();
    $modules = isset($extra['autoload-drupal']['modules']) ? $extra['autoload-drupal']['modules'] : [];
    $namespaces = [];
    array_map(function ($module) use (&$namespaces) {
      $constrain = [];
      $base_dir = $module;
      if (is_array($module)) {
        $constrain = isset($base_dir[1]) ? $base_dir[1] : [];
        $base_dir = $base_dir[0];
      }
      return $this->buildNamespace($namespaces, $base_dir, $constrain);
    }, $modules);

    return $namespaces;
  }

  /**
   * Build the autoload json to merge in.
   */
  public function buildAutoloadJson() {
    $composer_output = [
      'name' => 'fenetikm/autoload-drupal',
      'version' => '1.0',
      'autoload' => [
        'psr-4' => $this->buildNamespaces(),
        'classmap' => $this->buildClassmap(),
      ],
    ];

    $composer_json = json_encode($composer_output, JSON_PRETTY_PRINT);
    return $composer_json;
  }

  /**
   * Merge in the built autoload json.
   */
  public function mergeAutoload() {
    $root = $this->composer->getPackage();
    $new_autoload_json = $this->buildAutoloadJson();
    $new_autoload = json_decode($new_autoload_json, TRUE);
    $loader = new ArrayLoader();
    $package = $loader->load($new_autoload);

    $autoload = $package->getAutoload();
    $root->setAutoload(array_merge_recursive(
      $root->getAutoload(),
      $autoload
    ));
  }

}
