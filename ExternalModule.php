<?php
/**
 * @file
 * Provides ExternalModule class for Project Ownership module.
 */

namespace ProjectOwnership\ExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use Project;
use RCView;
use REDCapEntity\EntityDB;
use REDCapEntity\EntityFactory;
use Records;

/**
 * ExternalModule class for Project Ownership module.
 */
class ExternalModule extends AbstractExternalModule {

    /**
     * @inheritdoc
     */
    function redcap_every_page_before_render($project_id) {
        if (!defined('REDCAP_ENTITY_PREFIX')) {
            $this->delayModuleExecution();

            // Exits gracefully when REDCap Entity is not available.
            return;
        }

        if (strpos(PAGE, substr(APP_PATH_WEBROOT_PARENT, 1) . 'index.php') === 0 && !empty($_GET['action']) && $_GET['action'] == 'project_ownership') {
            $this->redirect($this->getUrl('plugins/ownership_list.php'));
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            return;
        }

        if (PAGE == 'ProjectGeneral/create_project.php' || (PAGE == 'ProjectGeneral/edit_project_settings.php' && empty($_GET['action']))) {
            // Saving onwership when project create or edit form is submitted.
            $this->saveProjectOwnership($project_id);
        }
    }

    function redcap_entity_types() {
        $types = [];

        $types['project_ownership'] = [
            'label' => 'Project Ownership',
            'label_plural' => 'Projects Ownership',
            'icon' => 'key',
            'class' => [
                'name' => 'ProjectOwnership\Entity\ProjectOwnership',
                'path' => 'classes/entity/ProjectOwnership.php',
            ],
            'properties' => [
                'pid' => [
                    'name' => 'Project',
                    'type' => 'project',
                    'required' => true,
                ],
                'username' => [
                    'name' => 'Owner user account',
                    'type' => 'user',
                ],
                'email' => [
                    'name' => 'Owner email',
                    'type' => 'email',
                ],
                'firstname' => [
                    'name' => 'Owner first name',
                    'type' => 'text',
                ],
                'lastname' => [
                    'name' => 'Owner last name',
                    'type' => 'text',
                ],
            ],
        ];

        return $types;
    }

    /**
     * @inheritdoc
     */
    function redcap_every_page_top($project_id) {
        if (!defined('REDCAP_ENTITY_PREFIX')) {
            $this->delayModuleExecution();

            // Exits gracefully when REDCap Entity is not enabled.
            return;
        }

        if (strpos(PAGE, 'ExternalModules/manager/control_center.php') !== false) {
            $this->includeJs('js/config.js');
            $this->setJsSettings(array('modulePrefix' => $this->PREFIX));

            return;
        }

        if (PAGE == 'ProjectSetup/index.php') {
            // Edit project form.
            $context = 'edit';
        }
        elseif (PAGE == 'ProjectGeneral/copy_project_form.php') {
            // Copy project form.
            $context = 'copy';
        }
        elseif (strpos(PAGE, substr(APP_PATH_WEBROOT_PARENT, 1) . 'index.php') === 0 && !empty($_GET['action'])) {
            if ($_GET['action'] == 'create') {
                // Create project form.
                $context = 'create';
            }
            elseif ($_GET['action'] == 'myprojects') {
                $helper = RCView::a(array('href' => APP_PATH_WEBROOT_PARENT . 'index.php?action=project_ownership'), 'Project Ownership List');
                $helper = 'To review and edit ownership of the projects you have access to, visit the ' . $helper . '.';

                $this->setJsSettings(array('ownershipListHelper' => RCView::div(array('class' => 'ownership-list-helper col-sm-12'), $helper)));
                $this->includeJs('js/my-projects.js');
                $this->includeCss('css/my-projects.css');
            }
        }

        if (!empty($context)) {
            $this->buildOwnershipFieldset($context, $project_id);
        }
    }

    /**
     * @inheritdoc
     */
    function redcap_module_system_enable($version) {
        // Making sure the module is enabled on all projects.
        $this->setSystemSetting(ExternalModules::KEY_ENABLED, true);

        // Building project ownership entity.
        EntityDB::buildSchema($this);
    }

    /**
     * @inheritdoc
     */
    function redcap_module_system_disable($version) {
        // Avoiding this table to be erased due to automatic module disable on
        // External Modules error handling.
        // TODO: remove it if and when this error handling becomes configurable.
        return;

        // Removes project onwership entity.
        EntityDB::dropSchema($this);
    }

    /**
     * Gets count of file uploads, saved attributes, and records of a given
     * project.
     */
    function getProjectStats($project_id) {
        $stats = array();

        $project_id = intval($project_id);
        $sql = 'SELECT COUNT(e.doc_id) count FROM redcap_edocs_metadata e
                LEFT JOIN redcap_docs_to_edocs dte ON dte.doc_id = e.doc_id
                LEFT JOIN redcap_docs d ON d.docs_id = dte.docs_id
                WHERE e.project_id = "' . $project_id . '" AND d.docs_id IS NULL';

        $count = $this->query($sql);
        $count = db_fetch_assoc($count);
        $stats['file_uploads_count'] = $count['count'];

        $count = $this->query('SELECT COUNT(*) count FROM redcap_data WHERE project_id = "' . $project_id . '"');
        $count = db_fetch_assoc($count);
        $stats['attr_count'] = $count['count'];

        global $Proj;

        $aux = $Proj;
        $Proj = new Project($project_id);
        $stats['records_count'] = Records::getRecordCount();
        $Proj = $aux;

        return $stats;
    }

    /**
     * Builds ownership fieldset.
     */
    protected function buildOwnershipFieldset($context, $project_id = null) {
        // Setting up default values.
        $po_data = array(
            'username' => '',
            'firstname' => '',
            'lastname' => '',
            'email' => '',
        );

        // Loading stored values.
        if ($project_id && ($entity = $this->getProjectOwnership($project_id))) {
            $po_data = $entity->getData();
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
            'context' => $context,
            'userId' => USERID,
            'userInfoAjaxPath' => $this->getUrl('plugins/user_info_ajax.php'),
            'fieldsetContents' => RCView::tr(array('id' => 'po-tr', 'valign' => 'top'), $output),
        );

        if ($context == 'copy') {
            global $lang;
            $settings['copyTitleErrorMsg'] = $lang['copy_project_11'];
        }
        elseif ($context == 'edit') {
            global $user_rights;
            $settings['openProjectEditPopup'] = $user_rights['design'] && !empty($_GET['open_project_edit_popup']);
        }

        $this->setJsSettings($settings);

        // Including JS and CSS files.
        $this->includeJs('js/owner-fieldset.js');
        $this->includeCss('css/owner-fieldset.css');
    }

    /**
     * Gets project ownership.
     */
    protected function getProjectOwnership($project_id) {
        $factory = new EntityFactory();

        if (!$results = $factory->query('project_ownership')->condition('pid', $project_id)->execute()) {
            return false;
        }

        return reset($results);
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

        $values = array('pid' => $project_id);
        foreach ($suffixes as $suffix) {
            $field_name = 'project_ownership_' . $suffix;

            if (!in_array($suffix, $required)) {
                $value = null;
            }
            elseif (!empty($_POST[$field_name])) {
                $value = $_POST[$field_name];
            }
            else {
                echo 'Missing ' . $suffix . ' field.';
                exit;
            }

            $values[$suffix] = $value;
            unset($_POST[$field_name]);
        }

        if (!$entity = $this->getProjectOwnership($project_id)) {
            $factory = new EntityFactory();
            $entity = $factory->getInstance('project_ownership');
        }

        if ($entity->setData($values)) {
            echo 'An error has occurred. Please contact administration or try again later.';
        }

        $entity->save();
    }

    /**
     * Includes a local CSS file.
     *
     * @param string $path
     *   The relative path to the css file.
     */
    function includeCss($path) {
        echo '<link rel="stylesheet" href="' . $this->getUrl($path) . '">';
    }

    /**
     * Includes a local JS file.
     *
     * @param string $path
     *   The relative path to the js file.
     */
    function includeJs($path) {
        echo '<script src="' . $this->getUrl($path) . '"></script>';
    }

    /**
     * Redirects user to the given URL.
     *
     * This function basically replicates redirect() function, but since EM
     * throws an error when an exit() is called, we need to adapt it to the
     * EM way of exiting.
     */
    protected function redirect($url) {
        if (headers_sent()) {
            // If contents already output, use javascript to redirect instead.
            echo '<script>window.location.href="' . $url . '";</script>';
        }
        else {
            // Redirect using PHP.
            header('Location: ' . $url);
        }

        $this->exitAfterHook();
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
