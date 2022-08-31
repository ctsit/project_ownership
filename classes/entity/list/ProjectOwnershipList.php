<?php

namespace ProjectOwnership\Entity;

require_once dirname(__DIR__) . '/../../ExternalModule.php';

use RedCapDB;
use RCView;
use REDCap;
use REDCapEntity\EntityList;
use ProjectOwnership\ExternalModule\ExternalModule;

class ProjectOwnershipList extends EntityList {

    function getFields() {
        global $lang;

        $fields = parent::getFields();

        foreach (['email', 'firstname', 'lastname'] as $key) {
            $fields[$key]['sql_field'] = 'IFNULL(e.' . $key . ', u.user_' . $key . ')';
        }

        // Prevent REDCap entity from trying to assign an empty email address
        $fields['email']['type'] = 'text';

        $fields['fullname'] = [
            'name' => 'Owner name',
            'type' => 'text',
            'sql_field' => 'CONCAT(IFNULL(e.firstname, u.user_firstname), " ", IFNULL(e.lastname, u.user_lastname))',
        ];

        $fields['type'] = [
            'name' => 'Project purpose',
            'type' => 'text',
            'sql_field' => 'p.purpose',
            'choices' => [
                RedCapDB::PURPOSE_PRACTICE => $lang['create_project_15'],
                RedCapDB::PURPOSE_OPS => $lang['create_project_16'],
                RedCapDB::PURPOSE_RESEARCH => $lang['create_project_17'],
                RedCapDB::PURPOSE_QUALITY => $lang['create_project_18'],
            ],
        ];

        $fields['pi'] = [
            'name' => 'PI name',
            'type' => 'text',
            'sql_field' => 'CONCAT(p.project_pi_firstname, " ", p.project_pi_lastname)',
        ];

        $fields['irb'] = [
            'name' => 'IRB #',
            'type' => 'text',
            'sql_field' => 'p.project_irb_number',
        ];

        $fields['last_activity'] = [
            'name' => 'Date of last activity',
            'type' => 'text',
            'sql_field' => 'p.last_logged_event',
        ];

        if (!SUPER_USER && !ACCOUNT_MANAGER) {
            $fields['is_project_manager'] = [
                'sql_field' => 'IFNULL(ur.design, up.design)',
            ];
        }

        return $fields;
    }

    function getColsLabels() {
        return parent::getColsLabels() + [
            'actions' => 'Actions',
        ];
    }

    function getExposedFilters() {
        if (!SUPER_USER && !ACCOUNT_MANAGER) {
            return [];
        }

        $filters = parent::getExposedFilters();
        unset($filters['last_activity']);

        return $filters;
    }

    function buildTableRow($data, $entity) {
        $row = parent::buildTableRow($data, $entity);

        // Don't show deleted projects
        $EM = new ExternalModule();
        $q = $EM->framework->query('SELECT date_deleted FROM redcap_projects WHERE project_id = ?', [$data['pid']]);
        $result = db_fetch_assoc($q);
        if ($result['date_deleted']) {
            // https://stackoverflow.com/a/2114029/7418735
            if (date('Y-m-d h:i:s') > $result['date_deleted']) return null;
        }

        if ($data['username'] && (SUPER_USER || ACCOUNT_MANAGER)) {
            $url = APP_PATH_WEBROOT . 'ControlCenter/view_users.php?username=' . REDCap::escapeHtml($data['username']);
            $row['fullname'] = RCView::a(['href' => $url, 'target' => '_blank'], $row['fullname']);
        }

        if ($data['email']) {
            $url = 'mailto:' . $data['email'];
            $row['email'] = RCView::a(['href' => $url, 'target' => '_blank'], $row['email']);
        }

        $row['last_activity'] = date_create($data['last_activity'])->format('m/d/Y');

        if (SUPER_USER || ACCOUNT_MANAGER || !empty($data['is_project_manager'])) {
            $url = APP_PATH_WEBROOT . 'ProjectSetup/index.php?pid=' . REDCap::escapeHtml($data['pid']) . '&open_project_edit_popup=1';
            $row['actions'] = RCView::a(['href' => $url, 'class' => 'btn btn-xs btn-success', 'target' => '_blank'], 'assign owner');
        } else {
            $row['actions'] = '-';
        }

        return $row;
    }

    function getQuery() {
        $query = parent::getQuery()
            ->join('redcap_user_information', 'u', 'u.username = e.username', 'LEFT')
            ->join('redcap_record_counts', 'c', 'c.project_id = e.pid', 'LEFT')
            ->join('redcap_projects', 'p', 'p.project_id = e.pid');

        if (!SUPER_USER && !ACCOUNT_MANAGER) {
            $query->join('redcap_user_rights', 'up', 'up.project_id = e.pid AND up.username = "' . db_escape(USERID) . '"');
            $query->join('redcap_user_roles', 'ur', 'up.role_id = ur.role_id', 'LEFT');
        }

        return $query;
    }

    function setCols($cols) {
        $EM = new ExternalModule();
        if ($EM->getSystemSetting('enable_uf_features')) {
            if ($EM->getSystemSetting("show_billable_column")) { array_push($cols, "billable"); }
            if ($EM->getSystemSetting("show_sequestered_column")) { array_push($cols, "sequestered"); }
        }

        return parent::setCols($cols);
    }

    function getSortableColumns() {
        return array_merge(parent::getSortableColumns(), ['pid', 'fullname', 'email', 'username', 'type', 'pi', 'irb', 'last_activity']);
    }

    protected function loadPageStyles() {
        $this->cssFiles[] = $this->module->getUrl('css/ownership-list.css');
        parent::loadPageStyles();
    }
}
