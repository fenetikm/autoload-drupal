# Drupal 8 Composer Autoloader

A Composer plugin to add Drupal 8 autoloading of modules to the composer autoloader.

## Why?

Why would you want this? Doesn't Drupal 8 have it's own autoloading mechanism?

There are quite a few handy types of tools (e.g. static analysis, intellisense) that rely on being able to load all classes via the composer autoloader. Unfortunately, as Drupal 8 does it's own autoloading at boot time, using these tools at best becomes slow (if you try to boot Drupal on the fly) or at worst becomes impossible (because you are running Drupal inside a VM). Some IDEs (e.g. PHPStorm) get around this by implementing their own discovery mechanism but if you are using an IDE or editor that doesn't do this you are out of luck.

This plugin plugs this gap.

## How

This plugin is heavily based off of the (Composer Merge Plugin)[https://github.com/wikimedia/composer-merge-plugin]. Essentially this plugin creates a `composer.json` file in memory including in all the specified modules to an `autoload` section and merges it into the root `composer.json` file.

## Installation

### Require the plugin in your `composer.json`

Standard stuff:

```json
{
    "require": {
        "fenetikm/autoload-drupal": "dev-master"
    }
}
```

### Configure modules to autoload

This plugin is configured via the `extra` section in your `composer.json`. Usually you would want the `app/modules/contrib/`, `app/core/modules/` and the `app/modules/custom/` directories included. As Drupal can be configured in many ways, none of this is assumed and so all must be added in.

You can also constrain which modules are added in from a directory by specifying an array of the pattern `[ "directory_to_include", [ "module1", "module2" ] ]`.

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
