# OpenEuropa Corporate Content Lock

This module provides the ability to prevent concurrent content editing on a site.
By installing this module, a user that starts to edit content on the site will lock it preventing other users from
editing it at the same time.

This lock can only be broken by the user that created it or by a user with the "Break content lock" permission, and it
can be done by either saving the content or by clicking the available links on the content editing page.

## Installation

Before enabling this module, make sure the [Content Lock](https://www.drupal.org/project/content_lock) module is present
in your codebase by adding it to your `composer.json` and by running `composer update`:

```json
"require": {
    "drupal/content_lock": "^3.0.0-alpha2"
}
```

## Default configuration

The module ships with default configuration that will be set when enabled. This will apply the content locking to
all content types.
