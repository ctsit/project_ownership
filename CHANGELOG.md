# Change Log
All notable changes to the Project Ownership module will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [1.2.1] - 2018-07-12
### Changed
- Add documentation that describes PI information autocomplete feature. (Dileep Rajput)
- Resolve edge case of auto_assign link failing to fill PI info the first time it is pressed on the project creation page (Dileep)
- Modify owner-fieldset.js to not overwrite old pi information (Dileep)


## [1.2.0] - 2018-06-01
### Changed
- Autocomplete pi info if project purpose is set to research and a REDCap username has been set (Dileep Rajput)
- Autofill pi fields whenever ownership fields are autofilled (Dileep Rajput)
- Move project ownership fields to before the project purpose field (Dileep Rajput)


## [1.1.3] - 2018-05-03
### Changed
- Preventing false alarms/errors on redirect. (Tiago Bember Simeao)


## [1.1.2] - 2018-05-02
### Changed
- Add documentation of the custom 'project_ownership' action (Philip Chase)
- Link to 'Project Ownership List' via the action on the 'My Projects' page (Philip Chase)
- Creating a static path for projects ownership page. (Tiago Bember Simeao)


## [1.1.1] - 2018-05-02
### Changed
- Finish rebranding with a change to the README (Philip Chase)


## [1.1.0] - 2018-05-02
### Added
- Adding ownership list plugin to provide a summer view of project ownership to users. (Tiago Bember Simeao)
- Add scripts and instructions for backfilling project ownership (Philip Chase)


## [1.0.3] - 2018-03-23
### Changed
- Set the collation of the redcap_project_ownership table to match REDCap's default of utf8_unicode_ci (Philip Chase)
- Fixing error on Additional Customizations save. (Tiago Bember Simeao)
- Enforcing module to be enabled for all projects. (Tiago Bember Simeao)


## [1.0.2] - 2018-03-23
### Changed
- Handling copy project case. (Tiago Bember Simeao)


## [1.0.1] - 2018-03-14
### Changed
- Fix path check on project creation page. (Tiago Bember Simeao)


## [1.0.0] - 2018-03-09
### Summary
- This is the first release of this module. It enforces the collection of ownership details at project creation.
