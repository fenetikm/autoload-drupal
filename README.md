# Drupal 8 Composer Autoloader

A Composer plugin to add Drupal 8 autoloading of modules to the composer autoloader.

## Why?

Why would you want this? Doesn't Drupal 8 have its own autoloading mechanism?

There are quite a few handy types of tools (e.g. static analysis, intellisense) that rely on being able to load all classes via the composer autoloader. Unfortunately, as Drupal 8 does its own autoloading at boot time, using these tools at best becomes slow (if you try to boot Drupal on the fly) or at worst becomes impossible (because you are running Drupal inside a VM). Some IDEs (e.g. PHPStorm) get around this by implementing their own discovery mechanism but if you are using an IDE or editor that doesn't do this you are out of luck.

This plugin plugs this gap.

## How

This plugin is heavily based off of the [Composer Merge Plugin](https://github.com/wikimedia/composer-merge-plugin). Essentially this plugin creates a `composer.json` file in memory including in all the specified modules to an `autoload` section and merges it into the root `composer.json` file, also in memory.

## Installation

### Require the plugin in your `composer.json`

Standard stuff:

```json
{
    "require": {
        "fenetikm/autoload-drupal": "0.2"
    }
}
```

### Configure modules to autoload

This plugin is configured via the `extra` section in your `composer.json`. Usually you would want the `app/modules/contrib/`, `app/core/modules/` and the `app/modules/custom/` directories included. As Drupal can be configured in many ways, none of this is assumed and so all must be added in.

You can also constrain which modules are added in from a directory by specifying an array that follows the pattern of `[ "directory_to_include", [ "module1", "module2" ] ]`.

For example:

```json
    "extra": {
        "autoload-drupal": {
            "modules": [
              "app/modules/contrib/",
              "app/core/modules/",
              [
                  "app/modules/custom/", [ "my_module" ]
              ]
            ]
        }
    }
```

Here, all modules in `app/modules/contrib/` are added in, all modules in `app/core/modules/` are added in and only the `my_module` module is added in from the `app/modules/custom/` directory.

**NOTE:** only modules with `src` directories will be added in (since they conform to PSR for class autoloading).

### Configure classmap

Additionally you may also add in classes that are outside of modules. A typical example is adding in the Drupal testing classes. e.g.

```json
    "extra": {
        "autoload-drupal": {
            "modules": [
                "app/modules/contrib/",
                "app/core/modules/",
                "app/modules/custom/",
            ],
            "classmap": [
                "app/core/tests/Drupal/Tests"
            ]
        }
    }
```

This will add in all the classes in that directory such as `UnitTestCase`, `AssertHelperTraitTest` etc.

## Rebuild the autoloader

The composer autoloader with the Drupal modules will get generated at install and update. To manually force the refresh of it, do:

```sh
composer dump-autoload
```

## Result

If you have set it up successfully you will see something like the following in your `vendor/composer/autoload_psr4.php` file:

```php
return array(
  'Drupal\\user\\' => array($baseDir . '/app/core/modules/user/src'),
  'Drupal\\my_module\\' => array($baseDir . '/app/modules/custom/my_module/src'),
  'Drupal\\rad-module\\' => array($baseDir . '/app/modules/contrib/rad-module/src'),
  'Drupal\\entity\\' => array($baseDir . '/app/core/modules/entity/src'),
  );
```
