<?php

require_once dirname(__DIR__) . '/classes/entity/list/ProjectOwnershipList.php';

use ProjectOwnership\Entity\ProjectOwnershipList;

$list = new ProjectOwnershipList('project_ownership', $module);
$list->render('global');
