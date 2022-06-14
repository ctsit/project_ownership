ALTER TABLE `redcap_entity_project_ownership` 
  ADD `billable` INT NULL DEFAULT NULL AFTER `lastname`,
  ADD `sequestered` INT NULL DEFAULT NULL AFTER `billable`;
  