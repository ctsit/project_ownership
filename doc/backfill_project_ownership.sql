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

truncate rcpo_test;

-- PI:
-- If owner is null and PI data is not null, then set owner to PI.
-- list the rows where pi email, fn, and ln are set. (1705)
select project_id, project_pi_username, project_pi_email, project_pi_firstname, project_pi_lastname from redcap_projects where project_pi_email is not null and project_pi_email != "";
-- Look for rows where pi usernames is set (0)
select project_id, project_pi_username from redcap_projects where project_pi_username is not null and project_pi_username != "";
-- List the owners to be set
select project_id, project_pi_username, project_pi_email, project_pi_firstname, project_pi_lastname
  from redcap_projects as rcp left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
  where project_pi_email is not null and project_pi_email != "" and (rcpo.email is null or rcpo.email = "");
-- set the owner to the PI
insert into rcpo_test (pid, username, email, firstname, lastname)
  select project_id, project_pi_username, project_pi_email, project_pi_firstname, project_pi_lastname
  from redcap_projects as rcp left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
  where project_pi_email is not null and project_pi_email != "" and (rcpo.email is null or rcpo.email = "");


-- Build a table of the usernames of peole who were professional, fulltime project creators.alter
-- These people should not own projects except as a last resort.  They create lots of projects and
-- they use a lot of projects, but they are owner of only a small percentage of their work.
drop table if exists paid_creators;
create table paid_creators (
  username  VARCHAR(128),
  primary key (username)
)
collate utf8_unicode_ci;

truncate paid_creators;

insert into paid_creators (username) values ("tls");
insert into paid_creators (username) values ("j.johnston");
insert into paid_creators (username) values ("cabernat");
insert into paid_creators (username) values ("c.holman");
insert into paid_creators (username) values ("swehmeyer");


-- Creator: If owner is null and creator is not suspended and creator is not in paid_creators, then set owner to creator
-- Enumerate creators
select rcp.project_id, rcui.username
from redcap_projects as rcp inner join redcap_user_information as rcui on (rcp.created_by = rcui.ui_id)
where created_by is not null;
-- show suspended column
select username,user_suspended_time from redcap_user_information;
-- List owners to be set
select rcp.project_id, rcui.username
from redcap_projects as rcp
  inner join redcap_user_information as rcui on (rcp.created_by = rcui.ui_id)
  left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
  left join paid_creators as pc on (pc.username = rcui.username)
where (rcpo.email is null or rcpo.email = "")
  and rcui.user_suspended_time is null
  and pc.username is null;
-- Set owner to creator
insert into rcpo_test (pid, username, email, firstname, lastname)
    select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname
    from redcap_projects as rcp
      inner join redcap_user_information as rcui on (rcp.created_by = rcui.ui_id)
      left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
      left join paid_creators as pc on (pc.username = rcui.username)
    where (rcpo.email is null or rcpo.email = "")
      and rcui.user_suspended_time is null
      and pc.username is null;


-- Fix collation in redcap_project_stats
alter table redcap_project_stats
CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;


-- Last_user is designer or authz manager:
--   If owner is null and last_user is not suspended and last_user is not in paid_creators, and last_user has design or user_rights permissions on project, then set owner to last_user
-- enumerate relevant permissions of the last unsuspended user by project
select rcp.project_id, rcps.last_user, rcur.design, rcur.user_rights, rcuro.design, rcuro.user_rights, rcui.user_suspended_time
from redcap_projects as rcp
inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
inner join redcap_user_information as rcui on (rcps.last_user = rcui.username)
left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id and rcps.last_user = rcur.username)
left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
left join paid_creators as pc on (pc.username = rcui.username)
left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
where (rcpo.email is null or rcpo.email = "")
    and rcui.user_suspended_time is null
    and pc.username is null
  and rcps.last_user is not null
  and (rcur.design = 1 or rcur.user_rights = 1 or rcuro.design = 1 or rcuro.user_rights = 1);

-- set owner to last unsuspended user with some perms
insert into rcpo_test (pid, username, email, firstname, lastname)
    select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname
    from redcap_projects as rcp
    inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
    inner join redcap_user_information as rcui on (rcps.last_user = rcui.username)
    left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id and rcps.last_user = rcur.username)
    left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
    left join paid_creators as pc on (pc.username = rcui.username)
    left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
    where (rcpo.email is null or rcpo.email = "")
        and rcui.user_suspended_time is null
        and pc.username is null
        and rcps.last_user is not null
        and (rcur.design = 1 or rcur.user_rights = 1 or rcuro.design = 1 or rcuro.user_rights = 1);

-- Any authz’d user who is not suspended: If owner is null and list of non-suspended, authz’d users is not null, then set owner to a random entry from that list.
-- Last_user is designer and authz manager but suspended: If owner is null and last_user is not in paid_creators, and last_user has design and user_rights permissions on project, then set owner to last_user
-- Last_user is designer or authz manager but suspended: If owner is null and last_user is not in paid_creators, and last_user has design or user_rights permissions on project, then set owner to last_user
-- Creator, but suspended: If owner is null and creator is not in paid_creators, then set owner to creator
-- Paid Creator: If owner is null and creator is not suspended and creator is in paid_creators, then set owner to creator

select * FROM RCPO_TEST;

select * from redcap_projects;
