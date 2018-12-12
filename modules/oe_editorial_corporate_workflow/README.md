# OpenEuropa Corporate Editorial Workflow

This module provides corporate editorial workflow features for the OpenEuropa project.
By installing this module, you will benefit from a complete content moderation workflow.
This module ships with the following features:
* Roles and permissions required for the workflow
* Workflow configured according to the corporate workflow with states, and transitions

## Setup workflow for content types

In order to be able to use the functionality you need to do the following:

* Create a content type
* Assign the content type specific permissions to the editorial roles (eg.: Content type X: Create new content)
* Go to "admin/config/workflow/workflows" and edit the Corporate workflow and assign your 
content type under "THIS WORKFLOW APPLIES TO:" to this workflow

After executing these steps as a site administrator, you should have the workflow applied to your desired content type.