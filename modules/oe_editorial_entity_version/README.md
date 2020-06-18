# OpenEuropa Corporate Editorial Entity Version

This module provides the integration between the [Editorial Corporate Workflow](https://github.com/openeuropa/oe_editorial/tree/master/modules/oe_editorial_corporate_workflow)
and the [Entity Version](https://github.com/openeuropa/entity_version) modules.
It installs and configures the version field for nodes and version number change configurations for the
corporate workflow.

The following rules are applied for each state transition:
- New draft:
  Minor version number increased if node values are changed.
- From Needs review to Draft:
  Minor version number increased if node values are changed.
- From Request validation to Draft:
  Minor version number increased if node values are changed.
- From Validated to Draft:
  Minor version number increased if node values are changed.
- From Published to Draft:
  Minor version number increased if node values are changed.
- From Archived to Draft:
  Minor version number increased if node values are changed.
- From Request validation to Validated:
  Increase Major version and reset the Minor version.

Each of these configurations can be changed under the Workflow settings.

The module overrides the core NodeRevisionRevertForm in order to ensure during revision revert following:
- Version numbers are correctly following the rules of the corporate workflow
- The next version becomes Draft
- The moderation log message indicates: "Version x.x.x has been restored by userX"
