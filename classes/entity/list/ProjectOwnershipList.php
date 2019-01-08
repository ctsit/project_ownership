<?php

namespace ProjectOwnership\Entity;

use Records;
use RedCapDB;
use RCView;
use REDCap;
use REDCapEntity\EntityList;

class ProjectOwnershipList extends EntityList {

    function getFields() {
        global $lang;

        $fields = parent::getFields();

        foreach (['email', 'firstname', 'lastname'] as $key) {
            $fields[$key]['sql_field'] = 'IFNULL(e.' . $key . ', u.user_' . $key . ')';
        }

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

    function getTableHeaderLabels() {
        $labels = parent::getTableHeaderLabels();

        return [
            'pid' => $labels['pid'],
            'fullname' => $labels['fullname'],
            'email' => $labels['email'],
            'username' => $labels['username'],
            'type' => $labels['type'],
            'pi' => $labels['pi'],
            'irb' => $labels['irb'],
            'last_activity' => $labels['last_activity'],
            'records_count' => 'Project records count',
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

        if ($data['username']) {
            $row['username'] = REDCap::escapeHtml($data['username']);

            if (SUPER_USER || ACCOUNT_MANAGER) {
                $url = APP_PATH_WEBROOT . 'ControlCenter/view_users.php?username=' . $row['username'];
                $row['username'] = RCView::a(['href' => $url, 'target' => '_blank'], $row['username']);
            }
        }

        $row['records_count'] = Records::getRecordCount($data['pid']);
        $row['last_activity'] = date_create($data['last_activity'])->format('m/d/Y');
        $row['actions'] = '-';

        if (SUPER_USER || ACCOUNT_MANAGER || !empty($data['is_project_manager'])) {
            $url = APP_PATH_WEBROOT . 'ProjectSetup/index.php?pid=' . REDCap::escapeHtml($data['pid']) . '&open_project_edit_popup=1';
            $row['actions'] = RCView::a(['href' => $url, 'class' => 'btn btn-xs btn-success', 'target' => '_blank'], 'assign owner');
        }

        return $row;
    }

    function getQuery() {
        $query = parent::getQuery()
            ->join('redcap_user_information', 'u', 'u.username = e.username', 'LEFT')
            ->join('redcap_projects', 'p', 'p.project_id = e.pid');

        if (!SUPER_USER && !ACCOUNT_MANAGER) {
            $query->join('redcap_user_rights', 'up', 'up.project_id = e.pid AND up.username = "' . db_escape(USERID) . '"');
            $query->join('redcap_user_roles', 'ur', 'up.role_id = ur.role_id', 'LEFT');
        }

        return $query;
    }

    function getSortableColumns() {
        return array_merge(parent::getSortableColumns(), ['pid', 'fullname', 'email', 'username', 'type', 'pi', 'irb', 'last_activity']);
    }

    protected function loadPageStyles() {
        $this->cssFiles[] = $this->module->getUrl('css/ownership-list.css');
        parent::loadPageStyles();
    }
}
