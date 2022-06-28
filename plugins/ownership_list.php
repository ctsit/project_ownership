<?php

require_once dirname(__DIR__) . '/classes/entity/list/ProjectOwnershipList.php';

use ProjectOwnership\Entity\ProjectOwnershipList;

$list = new ProjectOwnershipList('project_ownership', $module);
$list->setCols(['pid', 'fullname', 'email', 'type', 'pi', 'irb', 'last_activity'])
    ->render('global');
