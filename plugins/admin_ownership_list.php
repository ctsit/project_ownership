<?php

require_once dirname(__DIR__) . '/classes/entity/list/ProjectOwnershipList.php';

use ProjectOwnership\Entity\ProjectOwnershipList;

$list = new ProjectOwnershipList('project_ownership', $module);
$list->setCols(['pid', 'fullname', 'email', 'username', 'type', 'pi', 'irb', 'last_activity'])
    ->render('control_center');
