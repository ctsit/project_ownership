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

    function redcap_every_page_top($project_id) {
        if ((strpos(PAGE, 'redcap/index.php') === 0 && !empty($_GET['action']) && $_GET['action'] == 'create') || PAGE == 'ControlCenter/edit_project.php') {
            $this->buildOwnerFieldset();
        }
    }

    function redcap_module_system_enable($prefix, $version) {
        if ($this->PREFIX != $prefix) {
            return;
        }

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
                comments TEXT,
                PRIMARY KEY (pid)
            )';

        $this->query($sql);
    }

    function redcap_module_system_disable($prefix, $version) {
        if ($this->PREFIX != $prefix) {
            return;
        }

        $q = $this->query('SHOW TABLES LIKE "redcap_project_ownership"');
        if (!db_num_rows($q)) {
            return;
        }

        // Removes project onwership table.
        $this->query('DROP table redcap_project_ownership');
    }

    protected function buildOwnerFieldset() {
        $req_ast = RCView::span(array('class' => 'required-ast'), '*') . ' ';

        $data = RCView::span(array(), 'REDCap user (if applicable)') . RCView::br();
        $data .= RCView::text(array(
            'id' => 'project_ownership_username',
            'name' => 'project_ownership_username',
            'class' => 'x-form-text x-form-field',
            'value' => '',
        ));
        $data .= RCView::a(array('href' => '#', 'class' => 'project_ownership_auto_assign'), '(I am the owner)');

        $name_fields = '';
        foreach (array('firstname' => 'First name', 'lastname' => 'Last name') as $suffix => $label) {
            $field_name = 'project_ownership_' . $suffix;
            $name_fields .= RCView::div(array(), $req_ast . RCView::span(array(), $label) . RCView::br() . RCView::text(array(
                'id' => $field_name,
                'name' => $field_name,
                'class' => 'x-form-text x-form-field user-info',
                'value' => '',
            )));
        }

        $data .= RCView::div(array('class' => 'project_ownership_name_wrapper clearfix'), $name_fields);
        $data .= RCView::div(array(), $req_ast . RCView::span(array(), 'Email') . RCView::br() . RCView::text(array(
            'id' => 'project_ownership_email',
            'name' => 'project_ownership_email',
            'class' => 'x-form-text x-form-field user-info',
            'value' => '',
        )));

        $data = RCView::td(array('class' => 'cc_label'), 'Project Ownership') . RCView::td(array('class' => 'cc_data'), $data);

        $attrs = array(
            'id' => 'project_ownership-tr',
            'sq_id' => 'project_ownership',
            'valign' => 'top',
        );

        $this->setJsSettings(array('fieldsetContents' => RCView::tr($attrs, $data), 'userId' => USERID));
        $this->includeJs('js/owner-fieldset.js');
        $this->includeCss('css/owner-fieldset.css');
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
     * @param mixed $value
     *   The setting value.
     */
    protected function setJsSettings($settings) {
        echo '<script>projectOwnership = ' . json_encode($settings) . ';</script>';
    }
}
