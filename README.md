# REDCap Project Ownership
A REDCap module to enforce the collection of ownership details at project creation.

## Prerequisites
- REDCap >= 8.0.0

## Installation
- Clone this repo into `<redcap-root>/modules/project_ownership_v<version_number>`.
- Go to **Control Center > Manage External Modules** and enable Project Ownership.

## Collecting project ownership
The project ownership is collected on project creation page. 3 fields are required: first name, last name and email. Alternativelly, the user can set a REDCap username, so the required information is pulled from its user account.

![Project creation page](img/create_project.png)

### Ownership auto assign
Users may click on "I am the owner" link to auto assign the ownership.

![Ownership auto assign](img/auto_assign.gif)

### Autocomplete from PI information
If the purpose of the project is "Research", the ownership fields are auto completed as the the PI information is filled out.

![Autocomplete from PI information](img/pi_autocomplete.gif)

## Checking and updating project ownership
The same form that can be found at project creation page may be seen at project settings page. There, users are able to check and update onwership information.

![Project settings page](img/edit_project.png)
