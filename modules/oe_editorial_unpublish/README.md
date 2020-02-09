# OpenEuropa Editorial Unpublish

This module provides an additional local task for moderated content that allows users with the
appropriate permissions to unpublish it directly without having to go through
the edit page first. 

Unpublishing means moving the content to a state that has been configured in the workflow to change the status to 0.

The "Unpublish" task is added automatically for content if the following conditions are met:

* The bundle has content moderation enabled
* The content's latest revision is published.
* The current user has the required permissions to transition the content to an unpublished state.

# API

## Events

Modules may subscribe to the `UnpublishStatesEvent` to alter the list of states that are available on the *Unpublish* page.
