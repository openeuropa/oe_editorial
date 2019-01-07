# OpenEuropa Corporate Content Lock

This module provides the ability to prevent concurrent content editing on a site.
By installing this module, a user that starts to edit content on the site will lock it preventing other users from
editing it at the same time.

This lock can only be broken by the user that created it or by a user with the "Break content lock" permission, and it
can be done by either saving the content or by clicking the available links on the content edition page.

## Setup content lock for content

The module ships with default configuration that will be set when enabled. This will apply the content locking to
all content types and file types.

The default configuration can be change at any time by navigating to the module's configuration page
(located in admin/config/content/content_lock) and selecting the required content types.
The module also allows to apply the content lock to custom entities by using the same configuration form.