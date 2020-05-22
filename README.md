# OXID console moduleactivator

## Installation

`composer require proudcommerce/oxid-console-moduleactivator`

## Commands

pc:module:activator
--------------------

* Description: Activates multiple modules, based on a YAML file.

* Usage:

`vendor/bin/oe-console oe-console pc:module:activator [-s|--skipDeactivation] [-d|--clearModuleData] [--] <yaml-file>`

* Example (activate all modules defined in the YAML file basedon a white- or blacklist):

`vendor/bin/oe-console oe-console pc:module:activator -s ../modules.yml`

### YML file

```yaml
whitelist:
  1:
    - ocb_cleartmp
    - moduleinternals
    - custommodule1
    #- ddoevisualcms
    #- ddoewysiwyg
  2:
    - ocb_cleartmp
    - custommodule2
priorities:
  1:
    moduleinternals:
        1200
    ocb_cleartmp:
        950
installations:
    - source/modules/myvendor/custommodule1
    - source/modules/myvendor/custommodule2
```

Supports either a __"whitelist"__ or a __"blacklist"__ entry with multiple shop ids and the desired module ids to activate (whitelist) or to exclude from activation (blacklist).

If you want, you can also specify __a YAML string on the command line instead of a file__, e.g.:

```bash
oe-console pc:module:activator $'whitelist:
  1:
    - oepaypal
' --shop-id=1
```

### Module priorities

With __"priorities"__, you can define the order (per subshop) in which the modules will be activated. You can defined priorities per subshop, see example above.

### Installing modules prior to activation

By specifying an __"installations"__ array, you can make sure that the modules are really "installed" in the shop (modules can be installed via Composer or by calling e.g. `vendor/bin/oe-console oe:module:install-configuration source/modules/oxcom/ocbcleartmp`). If a module of that list is not yet installed, `oe:module:install-configuration` will be called (please note that you still have to __add the Composer namespace for autoloading__ in the main _composer.json_ e.g.). So this is an "edge-case", if you add e.g. custom project specific modules directly into the _"source/modules/"_ folder and add them manually to the main _composer.json_ (so you don't need to have a separate Composer repository for each module):

```json
    "autoload": {
        "psr-4": {
            "MyVendor\\CustomModule1\\": "./source/modules/myvendor/custommodule1",
            "MyVendor\\CustomModule2\\": "./source/modules/myvendor/custommodule2"
        }
    }
```

A __better alternative__ might be to use the ["wikimedia/composer-merge-plugin"](https://github.com/wikimedia/composer-merge-plugin) plugin, so that your module's _composer.json_ will be used directly.

### Arguments

**yaml:**

* Name: yaml
* Description: YAML module list filename or YAML string. The file path is relative to the shop "source/" path

### Options:

**skipDeactivation:**

* Name: `--skipDeactivation`
* Shortcut: `-s`
* Accept value: no
* Is value required: no
* Description: Skip deactivation of modules, only activate.
* Default: `false`

**clearModuleData:**

* Name: `--clearModuleData`
* Shortcut: `-d`
* Accept value: no
* Is value required: no
* Description: Clear module data in oxconfig.
* Default: `false`
