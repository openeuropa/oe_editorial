# OpenEuropa Corporate Content Lock

This module provides the ability to prevent concurrent content editing on a site.
By installing this module, a user that starts to edit content on the site will lock it preventing other users from
editing it at the same time.

This lock can only be broken by a user with the "Break content lock" permission, and it
can be done by either saving the content or by clicking the available links on the content editing page.

## Requirements
This module depends on the following contrib module:
* drupal/content_lock

## Default configuration

The module ships with default configuration that will be set when enabled. This will apply the content locking to
all content types.
