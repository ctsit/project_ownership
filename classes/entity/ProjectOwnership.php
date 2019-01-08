<?php

namespace ProjectOwnership\Entity;

use REDCapEntity\Entity;

class ProjectOwnership extends Entity {
    function validateProperty($key, $value) {
        if ($key != 'pid' || PAGE != 'ProjectGeneral/create_project.php') {
            return parent::validateProperty($key, $value);
        }

        // Overriding validation of project ID property on project creation.
        return !empty($value) && intval($value) == $value;
    }
}
