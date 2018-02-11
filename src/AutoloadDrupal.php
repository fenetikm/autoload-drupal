<?php

/**
 * @file
 * Add in autoloading of Drupal code into composer autoloader.
 *
 * Entry in composer.json extra section should look like:
 * {
 *     "extra": {
 *         "autoload-drupal": {
 *             "modules": [
 *                 "app/modules/contrib/*",
 *                 "app/modules/custom/my-module",
 *                 "app/core/modules"
 *             ]
 *         }
 *     }
 * }
 */

namespace fenetikm\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class AutoloadDrupal implements PluginInterface, EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    // @TODO stuff here.
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // @TODO just use what we need. Point to our stuff.
    return array(
      // Use our own constant to make this event optional. Once
      // composer-1.1 is required, this can use PluginEvents::INIT
      // instead.
      self::COMPAT_PLUGINEVENTS_INIT =>
      array('onInit', self::CALLBACK_PRIORITY),
      InstallerEvents::PRE_DEPENDENCIES_SOLVING =>
      array('onDependencySolve', self::CALLBACK_PRIORITY),
      PackageEvents::POST_PACKAGE_INSTALL =>
      array('onPostPackageInstall', self::CALLBACK_PRIORITY),
      ScriptEvents::POST_INSTALL_CMD =>
      array('onPostInstallOrUpdate', self::CALLBACK_PRIORITY),
      ScriptEvents::POST_UPDATE_CMD =>
      array('onPostInstallOrUpdate', self::CALLBACK_PRIORITY),
      ScriptEvents::PRE_AUTOLOAD_DUMP =>
      array('onInstallUpdateOrDump', self::CALLBACK_PRIORITY),
      ScriptEvents::PRE_INSTALL_CMD =>
      array('onInstallUpdateOrDump', self::CALLBACK_PRIORITY),
      ScriptEvents::PRE_UPDATE_CMD =>
      array('onInstallUpdateOrDump', self::CALLBACK_PRIORITY),
    );
  }

  /**
   * Generate the module namespaces.
   */
  public function moduleNamespaces() {

  }

  /**
   * Generate the autoload composer.json section.
   */
  public function generateJsonAutoload() {

  }

  /**
   * Handle an event callback for initialization.
   *
   * @param \Composer\EventDispatcher\Event $event
   */
  public function onInit(BaseEvent $event) {
    // @TODO load settings here.
    $this->state->loadSettings();
    // It is not possible to know if the user specified --dev or --no-dev
    // so assume it is false. The dev section will be merged later when
    // the other events fire.
    $this->state->setDevMode(false);
    $this->mergeFiles($this->state->getIncludes(), false);
    $this->mergeFiles($this->state->getRequires(), true);
  }

  /**
   * Load plugin settings
   */
  public function loadSettings() {
    // @TODO fix up this copy pasta.
    $extra = $this->composer->getPackage()->getExtra();
    $config = array_merge(
      array(
        'module' => array(),
        'require' => array(),
        'recurse' => true,
        'replace' => false,
        'ignore-duplicates' => false,
        'merge-dev' => true,
        'merge-extra' => false,
        'merge-extra-deep' => false,
        'merge-scripts' => false,
      ),
      isset($extra['merge-plugin']) ? $extra['merge-plugin'] : array()
    );
    $this->includes = (is_array($config['include'])) ?
      $config['include'] : array($config['include']);
    $this->requires = (is_array($config['require'])) ?
      $config['require'] : array($config['require']);
    $this->recurse = (bool)$config['recurse'];
    $this->replace = (bool)$config['replace'];
    $this->ignore = (bool)$config['ignore-duplicates'];
    $this->mergeDev = (bool)$config['merge-dev'];
    $this->mergeExtra = (bool)$config['merge-extra'];
    $this->mergeExtraDeep = (bool)$config['merge-extra-deep'];
    $this->mergeScripts = (bool)$config['merge-scripts'];
  }

  /**
   * Find configuration files matching the configured glob patterns and
   * merge their contents with the master package.
   *
   * @param array $patterns List of files/glob patterns
   * @param bool $required Are the patterns required to match files?
   * @throws MissingFileException when required and a pattern returns no
   *      results
   */
  protected function mergeFiles(array $patterns, $required = false)
  {
    $root = $this->composer->getPackage();
    $files = array_map(
      function ($files, $pattern) use ($required) {
        if ($required && !$files) {
          throw new MissingFileException(
            "merge-plugin: No files matched required '{$pattern}'"
          );
        }
        return $files;
      },
      array_map('glob', $patterns),
      $patterns
    );
    foreach (array_reduce($files, 'array_merge', array()) as $path) {
      $this->mergeFile($root, $path);
    }
  }

  /**
   * Read a JSON file and merge its contents
   *
   * @param RootPackageInterface $root
   * @param string $path
   */
  protected function mergeFile(RootPackageInterface $root, $path)
  {
    if (isset($this->loaded[$path]) ||
      (isset($this->loadedNoDev[$path]) && !$this->state->isDevMode())
    ) {
      $this->logger->debug(
        "Already merged <comment>$path</comment> completely"
      );
      return;
    }
    $package = new ExtraPackage($path, $this->composer, $this->logger);
    if (isset($this->loadedNoDev[$path])) {
      $this->logger->info(
        "Loading -dev sections of <comment>{$path}</comment>..."
      );
      $package->mergeDevInto($root, $this->state);
    } else {
      $this->logger->info("Loading <comment>{$path}</comment>...");
      $package->mergeInto($root, $this->state);
    }
    if ($this->state->isDevMode()) {
      $this->loaded[$path] = true;
    } else {
      $this->loadedNoDev[$path] = true;
    }
    if ($this->state->recurseIncludes()) {
      $this->mergeFiles($package->getIncludes(), false);
      $this->mergeFiles($package->getRequires(), true);
    }
  }

  /**
   * Handle an event callback for an install, update or dump command by
   * checking for "merge-plugin" in the "extra" data and merging package
   * contents if found.
   *
   * @param ScriptEvent $event
   */
  public function onInstallUpdateOrDump(ScriptEvent $event)
  {
    $this->state->loadSettings();
    $this->state->setDevMode($event->isDevMode());
    $this->mergeFiles($this->state->getIncludes(), false);
    $this->mergeFiles($this->state->getRequires(), true);
    if ($event->getName() === ScriptEvents::PRE_AUTOLOAD_DUMP) {
      $this->state->setDumpAutoloader(true);
      $flags = $event->getFlags();
      if (isset($flags['optimize'])) {
        $this->state->setOptimizeAutoloader($flags['optimize']);
      }
    }
  }
  /**
   * Handle an event callback following an install or update command. If our
   * plugin was installed during the run then trigger an update command to
   * process any merge-patterns in the current config.
   *
   * @param ScriptEvent $event
   */
  public function onPostInstallOrUpdate(ScriptEvent $event)
  {
    // @codeCoverageIgnoreStart
    if ($this->state->isFirstInstall()) {
      $this->state->setFirstInstall(false);
      $this->logger->info(
        '<comment>' .
        'Running additional update to apply merge settings' .
        '</comment>'
      );
      $config = $this->composer->getConfig();
      $preferSource = $config->get('preferred-install') == 'source';
      $preferDist = $config->get('preferred-install') == 'dist';
      $installer = Installer::create(
        $event->getIO(),
        // Create a new Composer instance to ensure full processing of
        // the merged files.
        Factory::create($event->getIO(), null, false)
      );
      $installer->setPreferSource($preferSource);
      $installer->setPreferDist($preferDist);
      $installer->setDevMode($event->isDevMode());
      $installer->setDumpAutoloader($this->state->shouldDumpAutoloader());
      $installer->setOptimizeAutoloader(
        $this->state->shouldOptimizeAutoloader()
      );
      if ($this->state->forceUpdate()) {
        // Force update mode so that new packages are processed rather
        // than just telling the user that composer.json and
        // composer.lock don't match.
        $installer->setUpdate(true);
      }
      $installer->run();
    }
    // @codeCoverageIgnoreEnd
  }

}
