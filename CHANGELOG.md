# Change Log
All notable changes to the Project Ownership module will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).


## [2.3.0] - 2022-09-06
### Added
- Allow edit of billable and sequestered booleans on Control Center page (Kyle Chesney)


## [2.2.0] - 2022-09-01
### Changed
- Update author to CTSI support listserv Add Kyle Chesney to AUTHORS.md in alphabetical order (Kyle Chesney)
- Update module to use Framework version 8 Update EM queries to use prepared statement syntax (Kyle Chesney)
- Stop showing deleted projects in project ownership UI (Philip Chase)


## [2.1.0] - 2022-06-28
### Added
- Add billable and sequestered flag (Kyle Chesney)
- Add configurable text field at the system level (Kyle Chesney)

### Changed
- Allow mailto from email column by overriding Entity default functionality (Kyle Chesney)
- Link to user account in owner name if viewer is SUPERURSER or ACCOUNT_MANAGER remove Owner Account column (Kyle Chesney)
- Remove records_count field (Kyle Chesney)


## [2.0.3] - 2019-07-29
### Added
- Fix issue #38 (Kyle Chesney)


## [2.0.2] - 2019-02-01
### Added
- Including Exception class namespace. (Tiago Bember Simeao)


## [2.0.1] - 2019-01-23
### Changed
- Move admin ownership list to its own URL to prevent permission errors for non-admins (Philip Chase)


## [2.0.0] - 2019-01-12
### Added
- Add Authors file (Philip Chase)
- Add DOI to README (Philip Chase)
- Add control center link to ownership list and set min redcap version to 8.7.0 (Philip Chase)
- Add migration script for 1.x to 2.x upgrade. (Tiago Bember Simeao)

### Changed
- Update backfilling scripts to conform to the new schema for storing project ownership data (Philip Chase)
- Update README for redcap_entity prereq and features (Philip Chase)
- Refactor for REDCap Entity 2.0.0. (Tiago Bember Simeao)
- Include default value for $project_id in redcap_every_page_before_render to fix Issue #26. (Marly Cormar)


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
