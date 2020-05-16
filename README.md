# oxid-console-moduleactivator

## Commands

pc:module:activator
--------------------

* Description: Activates multiple modules, based on a YAML file.
* Usage:

  * `oe-console pc:module:activator [-s|--skipDeactivation] [-d|--clearModuleData] [--] <yaml-file>`

usage:
oe-console pc:module:activator ../modules.yml
- to activate all modules defined in the YAML file based
on a white- or blacklist

Example:

```yaml
whitelist:
1:
    - ocb_cleartmp
    - moduleinternals
    #- ddoevisualcms
    #- ddoewysiwyg
2:
    - ocb_cleartmp
priorities:
1:
    moduleinternals:
        1200
    ocb_cleartmp:
        950
```

Supports either a __"whitelist"__ or a __"blacklist"__ entry with multiple shop ids and the desired module ids to activate (whitelist) or to exclude from activation (blacklist).

With "priorities", you can define the order (per subshop) in which the modules will be activated.

If you want, you can also specify __a YAML string on the command line instead of a file__, e.g.:

```bash
oe-console pc:module:activator $'whitelist:
  1:
    - oepaypal
' --shop-id=1
```

### Arguments:

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
