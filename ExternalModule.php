<?php
/**
 * @file
 * Provides ExternalModule class for Project Ownership module.
 */

namespace ProjectOwnership\ExternalModule;

use Exception;
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
    function redcap_every_page_before_render($project_id = null) {
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
            'label_plural' => 'Project Owners',
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
            ]
        ];

        if ($this->getSystemSetting('enable_uf_features')) {
               $types['project_ownership']['properties']['billable'] = [
                    'name' => 'Billable',
                    'type' => 'boolean'
                ];
                $types['project_ownership']['properties']['sequestered'] = [
                    'name' => 'Sequestered',
                    'type' => 'boolean',
                ];
            }

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
            $this->setJsSettings(['modulePrefix' => $this->PREFIX]);

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
                $helper = RCView::a(['href' => APP_PATH_WEBROOT_PARENT . 'index.php?action=project_ownership'], 'Project Ownership List');
                $helper = 'To review and edit ownership of the projects you have access to, visit the ' . $helper . '.';

                $this->setJsSettings(['ownershipListHelper' => RCView::div(['class' => 'ownership-list-helper col-sm-12'], $helper)]);
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
        $this->checkModuleDependencies();

        // Making sure the module is enabled on all projects.
        $this->setSystemSetting(ExternalModules::KEY_ENABLED, true);

        // Building project ownership entity.
        EntityDB::buildSchema($this->PREFIX);
    }

    /**
     * @inheritdoc
     */
    function redcap_module_system_change_version($version, $old_version) {
        $this->checkModuleDependencies();

        // Making sure we are upgrading from v1.x to 2.x or greater.
        if (strpos($old_version, 'v1') !== 0 || !is_numeric($version[1]) || $version[1] < 2) {
            return;
        }

        // Making sure project ownership entity has not been initialized yet.
        if (db_query('SELECT 1 FROM redcap_entity_project_ownership LIMIT 1')) {
            return;
        }

        // Creating project ownership table.
        EntityDB::buildSchema($this->PREFIX);

        // Getting legacy ownership entries.
        if (!$q = $this->framework->query('SELECT * FROM redcap_project_ownership', [])) {
            return;
        }

        if (!db_num_rows($q)) {
            return;
        }

        // Migrating projects ownership.
        $factory = new EntityFactory();
        while ($result = db_fetch_assoc($q)) {
            $factory->create('project_ownership', $result);
        }
    }

    /**
     * Checks for module dependencies.
     *
     * @throws Exception
     *   Throws an error if dependencies do not meet. Do nothing otherwise.
     */
    function checkModuleDependencies() {
        // Making sure REDCap Entity is enabled.
        if (!defined('REDCAP_ENTITY_PREFIX')) {
            throw new Exception('Project Ownership requires REDCap Entity to work.');
        }
    }

    /**
     * Builds ownership fieldset.
     */
    protected function buildOwnershipFieldset($context, $project_id = null) {
        if (!defined('USERID')) { return; } // prevent error email from unauthorized page load attempts
        // Setting up default values.
        $po_data = [
            'username' => '',
            'firstname' => '',
            'lastname' => '',
            'email' => '',
        ];

        // Loading stored values.
        if ($project_id && ($entity = $this->getProjectOwnership($project_id))) {
            $po_data = $entity->getData();
        }

        // Required field marker.
        $req_ast = RCView::span(['class' => 'required-ast'], '*') . ' ';

        // Username field.
        $output = RCView::span([], 'REDCap username (if applicable)') . RCView::br();
        $output .= RCView::text([
            'id' => 'project_ownership_username',
            'name' => 'project_ownership_username',
            'class' => 'x-form-text x-form-field po-row',
            'placeholder' => 'Search',
            'value' => $po_data['username'],
        ]);

        // Adding search icon to username field.
        $output .= RCView::img(['class' => 'search-icon', 'src' => APP_PATH_IMAGES . 'magnifier.png']);

        // Adding ownership auto assign link.
        $output .= RCView::a(['href' => '#', 'class' => 'po-auto-assign'], '(I am the owner)');

        // Adding helper text to username field.
        $output .= RCView::div(['class' => 'newdbsub po-row'], 'If the project owner does not have a REDCap account, leave this field blank and fill the information manually below.');

        // Building first and last name fields.
        $name_fields = '';
        foreach (['firstname' => 'First name', 'lastname' => 'Last name'] as $suffix => $label) {
            $field_name = 'project_ownership_' . $suffix;
            $name_fields .= RCView::div([], $req_ast . RCView::span(['class' => 'po-info-label'], $label) . RCView::br() . RCView::text([
                'id' => $field_name,
                'name' => $field_name,
                'class' => 'x-form-text x-form-field po-required-info',
                'value' => $po_data[$suffix],
            ]));
        }

        // Wrapping first and last name fields into a single row.
        $output .= RCView::div(['class' => 'po-name-wrapper clearfix'], $name_fields);

        // Email field.
        $output .= RCView::div([], $req_ast . RCView::span(['class' => 'po-info-label'], 'Email') . RCView::br() . RCView::text([
            'id' => 'project_ownership_email',
            'name' => 'project_ownership_email',
            'class' => 'x-form-text x-form-field po-required-info po-row',
            'value' => $po_data['email'],
        ]));

        // Configurable text field.
        if ($this->getSystemSetting("additional_text_toggle")) {
            $output .= RCView::div(
                ['class' => 'po-row'],
                $this->getSystemSetting("additional_text")
            );
        }

        // Fieldset title.
        $output = RCView::td(['class' => 'po-label po-col'], 'Project Ownership') . RCView::td(['class' => 'po-col'], $output);

        // Passing fieldset content to JS.
        $settings = [
            'context' => $context,
            'userId' => USERID,
            'userInfoAjaxPath' => $this->getUrl('plugins/user_info_ajax.php'),
            'fieldsetContents' => RCView::tr(['id' => 'po-tr', 'valign' => 'top'], $output),
        ];

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
            $q = $this->framework->query('SELECT project_id FROM redcap_projects ORDER BY project_id DESC LIMIT 1', []);

            if (db_num_rows($q)) {
                $row = db_fetch_assoc($q);
                $project_id += $row['project_id'];
            }
        }

        // Specifying required fields for each case.
        $suffixes = ['username', 'firstname', 'lastname', 'email'];
        $required = empty($_POST['project_ownership_username']) ? ['firstname', 'lastname', 'email'] : ['username'];

        $values = ['pid' => $project_id];
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
