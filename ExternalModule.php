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

    function redcap_every_page_before_render() {
        if (in_array(PAGE, array('ControlCenter/edit_project.php', 'ProjectGeneral/create_project.php')) && $_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->saveProjectOwnership();
        }
    }

    function redcap_every_page_top($project_id) {
        if ((strpos(PAGE, 'redcap/index.php') === 0 && !empty($_GET['action']) && $_GET['action'] == 'create') || PAGE == 'ControlCenter/edit_project.php') {
            $this->buildOwnerFieldset(empty($_GET['project']) ? null : $_GET['project']);
        }
    }

    protected function getProjectOwnership($project_id) {
        $q = $this->query('SELECT * FROM redcap_project_ownership WHERE pid = ' . db_escape($project_id));
        if (!db_num_rows($q)) {
            return false;
        }

        return db_fetch_assoc($q);
    }

    protected function saveProjectOwnership() {
        if (empty($_GET['project'])) {
            $q = $this->query('SELECT project_id FROM redcap_projects ORDER BY project_id DESC LIMIT 1');
            $pid = 1;

            if (db_num_rows($q)) {
                $row = db_fetch_assoc($q);
                $pid += $row['project_id'];
            }
        }
        else {
            $pid = db_escape($_GET['project']);
        }

        $suffixes = array('username', 'firstname', 'lastname', 'email', 'comments');

        $values = array();
        if ($this->getProjectOwnership($pid)) {
            foreach ($suffixes as $suffix) {
                $field_name = 'project_ownership_' . $suffix;
                $values[] = $suffix . ' = ' . (empty($_POST[$field_name]) ? 'null' : '"' . db_escape($_POST[$field_name]) . '"');
                unset($_POST[$field_name]);
            }

            $this->query('UPDATE redcap_project_ownership SET ' . implode(', ', $values) . ' WHERE pid = ' . $pid);
        }
        else {
            $values['pid'] = $pid;
            foreach ($suffixes as $suffix) {
                $field_name = 'project_ownership_' . $suffix;
                $values[$suffix] = empty($_POST[$field_name]) ? 'null' : '"' . db_escape($_POST[$field_name]) . '"';
                unset($_POST[$field_name]);
            }

            $this->query('INSERT INTO redcap_project_ownership (' . implode(', ', array_keys($values)) . ') VALUES (' . implode(', ', $values) . ')');
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

    protected function buildOwnerFieldset($project_id = null) {
        $project_ownership = array('username' => '', 'firstname' => '', 'lastname' => '', 'email' => '');
        if ($project_id) {
            $project_ownership = $this->getProjectOwnership($project_id);
        }

        $req_ast = RCView::span(array('class' => 'required-ast'), '*') . ' ';

        $data = RCView::span(array(), 'REDCap username (if applicable)') . RCView::br();
        $data .= RCView::text(array(
            'id' => 'project_ownership_username',
            'name' => 'project_ownership_username',
            'class' => 'x-form-text x-form-field',
            'placeholder' => 'Search',
            'value' => $project_ownership['username'],
        ));

        $data .= RCView::img(array('class' => 'search-icon', 'src' => APP_PATH_IMAGES . 'magnifier.png'));
        $data .= RCView::a(array('href' => '#', 'class' => 'project_ownership_auto_assign'), '(I am the owner)');
        $data .= RCView::div(array('class' => 'newdbsub'), 'If the project owner does not have a REDCap account, fill the information manually below.');

        $name_fields = '';
        foreach (array('firstname' => 'First name', 'lastname' => 'Last name') as $suffix => $label) {
            $field_name = 'project_ownership_' . $suffix;
            $name_fields .= RCView::div(array(), $req_ast . RCView::span(array('class' => 'owner-info-label'), $label) . RCView::br() . RCView::text(array(
                'id' => $field_name,
                'name' => $field_name,
                'class' => 'x-form-text x-form-field owner-required-info',
                'value' => $project_ownership[$suffix],
            )));
        }

        $data .= RCView::div(array('class' => 'project_ownership_name_wrapper clearfix'), $name_fields);
        $data .= RCView::div(array(), $req_ast . RCView::span(array('class' => 'owner-info-label'), 'Email') . RCView::br() . RCView::text(array(
            'id' => 'project_ownership_email',
            'name' => 'project_ownership_email',
            'class' => 'x-form-text x-form-field owner-required-info',
            'value' => $project_ownership['email'],
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
