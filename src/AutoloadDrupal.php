<?php

/**
 * @file
 * Add in autoloading of Drupal code into composer autoloader.
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

}
