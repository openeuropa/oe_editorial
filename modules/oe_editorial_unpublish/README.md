# OpenEuropa Editorial Unpublish

This module provides an additional local tab for moderated content that allows users with the
appropriate permissions to unpublish a node directly without having to go through
the edit node first.

The "Unpublish" tab is added automatically for nodes if the following conditions are met:

1) The bundle needs to have content moderation.
2) The node has a published version.
3) The current user has the required permissions to transition the node to an unpublished state.

# API

## Events
Modules may subscribe to the provided UnpublishStatesEvent to alter the
list of states that are available to select when unpublishing a node.
