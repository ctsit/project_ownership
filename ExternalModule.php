<?php
/**
 * @file
 * Provides ExternalModule class for Project Ownership module.
 */

namespace ProjectOwnership\ExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use RCView;

/**
 * ExternalModule class for Project Ownership module.
 */
class ExternalModule extends AbstractExternalModule {

    /**
     * @inheritdoc
     */
    function redcap_every_page_before_render($project_id) {
        // Saving onwership when project create or edit form is submitted.
        if (in_array(PAGE, array('ProjectGeneral/edit_project_settings.php', 'ProjectGeneral/create_project.php')) && $_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->saveProjectOwnership($project_id);
        }
    }

    /**
     * @inheritdoc
     */
    function redcap_every_page_top($project_id) {
        // The ownership fieldset is only placed on create and edit project forms.
        if ((strpos(PAGE, substr(APP_PATH_WEBROOT_PARENT, 1) . 'index.php') === 0 && !empty($_GET['action']) && $_GET['action'] == 'create') || PAGE == 'ProjectSetup/index.php') {
            $this->buildOwnershipFieldset($project_id);
        }
    }

    /**
     * @inheritdoc
     */
    function redcap_module_system_enable($version) {
        $q = $this->query('SHOW TABLES LIKE "redcap_project_ownership"');
        if (db_num_rows($q)) {
            return;
        }

        // Creates project ownership table.
        $sql = '
            CREATE TABLE redcap_project_ownership (
                pid INT NOT NULL,
                username VARCHAR(128),
                email VARCHAR(128),
                firstname VARCHAR(128),
                lastname VARCHAR(128),
                PRIMARY KEY (pid)
            )';

        $this->query($sql);
    }

    /**
     * @inheritdoc
     */
    function redcap_module_system_disable($version) {
        // Avoiding this table to be erased due to automatic module disable on
        // External Modules error handling.
        // TODO: remove it if and when this error handling becomes configurable.
        return;

        $q = $this->query('SHOW TABLES LIKE "redcap_project_ownership"');
        if (!db_num_rows($q)) {
            return;
        }

        // Removes project onwership table.
        $this->query('DROP table redcap_project_ownership');
    }

    /**
     * Builds ownership fieldset.
     */
    protected function buildOwnershipFieldset($project_id = null) {
        /**
         * The method call below is a workaround to be used until the following
         * pull request is merged and released.
         *
         * https://github.com/vanderbilt/redcap-external-modules/pull/74
         *
         * TODO: remove it when it is not needed anymore.
         */
        $this->redcap_module_system_enable($this->VERSION);

        // Setting up default values.
        $po_data = array(
            'username' => '',
            'firstname' => '',
            'lastname' => '',
            'email' => '',
        );

        if ($project_id) {
            // Loading stored values.
            $po_data = $this->getProjectOwnership($project_id);
        }

        // Required field marker.
        $req_ast = RCView::span(array('class' => 'required-ast'), '*') . ' ';

        // Username field.
        $output = RCView::span(array(), 'REDCap username (if applicable)') . RCView::br();
        $output .= RCView::text(array(
            'id' => 'project_ownership_username',
            'name' => 'project_ownership_username',
            'class' => 'x-form-text x-form-field po-row',
            'placeholder' => 'Search',
            'value' => $po_data['username'],
        ));

        // Adding search icon to username field.
        $output .= RCView::img(array('class' => 'search-icon', 'src' => APP_PATH_IMAGES . 'magnifier.png'));

        // Adding ownership auto assign link.
        $output .= RCView::a(array('href' => '#', 'class' => 'po-auto-assign'), '(I am the owner)');

        // Adding helper text to username field.
        $output .= RCView::div(array('class' => 'newdbsub po-row'), 'If the project owner does not have a REDCap account, leave this field blank and fill the information manually below.');

        // Building first and last name fields.
        $name_fields = '';
        foreach (array('firstname' => 'First name', 'lastname' => 'Last name') as $suffix => $label) {
            $field_name = 'project_ownership_' . $suffix;
            $name_fields .= RCView::div(array(), $req_ast . RCView::span(array('class' => 'po-info-label'), $label) . RCView::br() . RCView::text(array(
                'id' => $field_name,
                'name' => $field_name,
                'class' => 'x-form-text x-form-field po-required-info',
                'value' => $po_data[$suffix],
            )));
        }

        // Wrapping first and last name fields into a single row.
        $output .= RCView::div(array('class' => 'po-name-wrapper clearfix'), $name_fields);

        // Email field.
        $output .= RCView::div(array(), $req_ast . RCView::span(array('class' => 'po-info-label'), 'Email') . RCView::br() . RCView::text(array(
            'id' => 'project_ownership_email',
            'name' => 'project_ownership_email',
            'class' => 'x-form-text x-form-field po-required-info po-row',
            'value' => $po_data['email'],
        )));

        // Fieldset title.
        $output = RCView::td(array('class' => 'po-label po-col'), 'Project Ownership') . RCView::td(array('class' => 'po-col'), $output);

        // Passing fieldset content to JS.
        $settings = array(
            'projectId' => $project_id,
            'userId' => USERID,
            'userInfoAjaxPath' => $this->getUrl('plugins/user_info_ajax.php'),
            'fieldsetContents' => RCView::tr(array('id' => 'po-tr', 'valign' => 'top'), $output),
        );

        $this->setJsSettings($settings);

        // Including JS and CSS files.
        $this->includeJs('js/owner-fieldset.js');
        $this->includeCss('css/owner-fieldset.css');
    }

    /**
     * Gets project ownership.
     */
    protected function getProjectOwnership($project_id) {
        $q = $this->query('SELECT * FROM redcap_project_ownership WHERE pid = ' . db_escape($project_id));
        if (!db_num_rows($q)) {
            return false;
        }

        return db_fetch_assoc($q);
    }

    /**
     * Saves project ownership.
     */
    protected function saveProjectOwnership($project_id) {
        if (!$project_id) {
            $project_id = 1;

            // If we are at project creation page, we need to calculate the ID
            // of the project being created.
            //
            // TODO: improve this, since concurrent requests can lead to
            // inconsistencies.
            $q = $this->query('SELECT project_id FROM redcap_projects ORDER BY project_id DESC LIMIT 1');

            if (db_num_rows($q)) {
                $row = db_fetch_assoc($q);
                $project_id += $row['project_id'];
            }
        }

        // Specifying required fields for each case.
        $suffixes = array('username', 'firstname', 'lastname', 'email');
        $required = empty($_POST['project_ownership_username']) ? array('firstname', 'lastname', 'email') : array('username');
        $ownership_exists = $this->getProjectOwnership($project_id);

        $values = array();
        foreach ($suffixes as $suffix) {
            $field_name = 'project_ownership_' . $suffix;

            if (!in_array($suffix, $required)) {
                $value = 'null';
            }
            elseif (!empty($_POST[$field_name])) {
                $value = '"' . db_escape($_POST[$field_name]) . '"';
            }
            else {
                echo 'Missing ' . $suffix . ' field.';
                exit;
            }

            $values[$suffix] = $ownership_exists ? $suffix . ' = ' . $value : $value;
            unset($_POST[$field_name]);
        }

        if ($ownership_exists) {
            // Updating an existing entry.
            $sql = 'UPDATE redcap_project_ownership SET ' . implode(', ', $values) . ' WHERE pid = ' . $project_id;
        }
        else {
            // Creating a new entry.
            $values['pid'] = $project_id;
            $sql = 'INSERT INTO redcap_project_ownership (' . implode(', ', array_keys($values)) . ') VALUES (' . implode(', ', $values) . ')';
        }

        $this->query($sql);
    }

    /**
     * Includes a local CSS file.
     *
     * @param string $path
     *   The relative path to the css file.
     */
    protected function includeCss($path) {
        echo '<link rel="stylesheet" href="' . $this->getUrl($path) . '">';
    }

    /**
     * Includes a local JS file.
     *
     * @param string $path
     *   The relative path to the js file.
     */
    protected function includeJs($path) {
        echo '<script src="' . $this->getUrl($path) . '"></script>';
    }

    /**
     * Sets JS settings.
     *
     * @param mixed $settings
     *   The setting settings.
     */
    protected function setJsSettings($settings) {
        echo '<script>projectOwnership = ' . json_encode($settings) . ';</script>';
    }
}
