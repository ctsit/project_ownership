-- Backfill project ownership on an old REDCap system.
-- Use a series of queries to do a best guess of the most authoritative or active person on the project.
--
-- Requirements
-- These queries require the last_user concept added via the Report Production Candidates module.  See https://github.com/ctsit/report_production_candidates

-- Create a temporary table for testing ownership backfill operations
CREATE TABLE rcpo_test (
    pid INT NOT NULL,
    username VARCHAR(128),
    email VARCHAR(128),
    firstname VARCHAR(128),
    lastname VARCHAR(128),
    PRIMARY KEY (pid)
);


-- PI:
-- If owner is null and PI data is not null, then set owner to PI.
-- list the rows where pi email, fn, and ln are set. (1705)
select project_id, project_pi_username, project_pi_email, project_pi_firstname, project_pi_lastname from redcap_projects where project_pi_email is not null and project_pi_email != "";
-- Look for rows where pi usernames is set (0)
select project_id, project_pi_username from redcap_projects where project_pi_username is not null and project_pi_username != "";
-- List the owners to be set
select project_id, project_pi_username, project_pi_email, project_pi_firstname, project_pi_lastname
  from redcap_projects as rcp left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
  where project_pi_email is not null and project_pi_email != "" and (rcpo.email is null or rcpo.email != "");
-- set the owner to the PI
insert into rcpo_test (pid, username, email, firstname, lastname)
  select project_id, project_pi_username, project_pi_email, project_pi_firstname, project_pi_lastname
  from redcap_projects as rcp left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
  where project_pi_email is not null and project_pi_email != "" and (rcpo.email is null or rcpo.email != "");


-- Creator: If owner is null and creator is not suspended and creator is not in paid_creators, then set owner to creator
-- Last_user is designer and authz manager: If owner is null and last_user is not suspended and last_user is not in paid_creators, and last_user has design and user_rights permissions on project, then set owner to last_user
-- Last_user is designer or authz manager: If owner is null and last_user is not suspended and last_user is not in paid_creators, and last_user has design or user_rights permissions on project, then set owner to last_user
-- Any authz’d user who is not suspended: If owner is null and list of non-suspended, authz’d users is not null, then set owner to a random entry from that list.
-- Last_user is designer and authz manager but suspended: If owner is null and last_user is not in paid_creators, and last_user has design and user_rights permissions on project, then set owner to last_user
-- Last_user is designer or authz manager but suspended: If owner is null and last_user is not in paid_creators, and last_user has design or user_rights permissions on project, then set owner to last_user
-- Creator, but suspended: If owner is null and creator is not in paid_creators, then set owner to creator
-- Paid Creator: If owner is null and creator is not suspended and creator is in paid_creators, then set owner to creator
