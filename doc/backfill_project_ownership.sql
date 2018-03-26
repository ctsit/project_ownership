-- Backfill project ownership on an old REDCap system.
-- Use a series of queries to do a best guess of the most authoritative or active person on the project.
--
-- Requirements
-- These queries require the last_user concept added via the Report Production Candidates module.  See https://github.com/ctsit/report_production_candidates

-- Fix collation in redcap_project_stats
alter table redcap_project_stats
CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;


-- Fix collation in redcap_project_ownership
alter table redcap_project_ownership
CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;


-- PI:
-- If owner is null and PI data is not null, then set owner to PI.
-- set the owner to the PI
insert into redcap_project_ownership (pid, username, email, firstname, lastname)
  select project_id, project_pi_username, project_pi_email, project_pi_firstname, project_pi_lastname
  from redcap_projects as rcp left join redcap_project_ownership as rcpo on (rcp.project_id = rcpo.pid)
  where project_pi_email is not null and project_pi_email != ""
  and (rcpo.email is null or rcpo.email = "")
  and (rcpo.username is null or rcpo.username = "");


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


-- Creator: If owner is null and creator who logged-in during the last 180 days, is not suspended and creator is not in paid_creators, then set owner to creator
-- Set owner to creator
insert into redcap_project_ownership (pid, username, email, firstname, lastname)
    select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname
    from redcap_projects as rcp
      inner join redcap_user_information as rcui on (rcp.created_by = rcui.ui_id)
      left join redcap_project_ownership as rcpo on (rcp.project_id = rcpo.pid)
      left join paid_creators as pc on (pc.username = rcui.username)
    where (rcpo.email is null or rcpo.email = "")
      and (rcpo.username is null or rcpo.username = "")
      and rcui.user_suspended_time is null
      and datediff(now(), rcui.user_lastlogin) < 180
      and pc.username is null;


-- Fix collation in redcap_project_stats
alter table redcap_project_stats
CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;


-- Last_user is designer or authz manager:
--   If owner is null and last_user is not suspended and last_user is not in paid_creators, and last_user has design or user_rights permissions on project, then set owner to last_user
-- set owner to last unsuspended user with some perms who logged-in during the last 180 days
insert into redcap_project_ownership (pid, username, email, firstname, lastname)
    select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname
    from redcap_projects as rcp
    inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
    inner join redcap_user_information as rcui on (rcps.last_user = rcui.username)
    left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id and rcps.last_user = rcur.username)
    left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
    left join paid_creators as pc on (pc.username = rcui.username)
    left join redcap_project_ownership as rcpo on (rcp.project_id = rcpo.pid)
    where (rcpo.email is null or rcpo.email = "")
        and (rcpo.username is null or rcpo.username = "")
        and rcui.user_suspended_time is null
        and datediff(now(), rcui.user_lastlogin) < 180
        and pc.username is null
        and rcps.last_user is not null
        and (rcur.design = 1 or rcur.user_rights = 1 or rcuro.design = 1 or rcuro.user_rights = 1);


-- Any authz’d user who is not suspended: If owner is null and list of non-suspended, authz’d users is not null, then set owner to a random entry from that list.
-- set owner to the most recently logged non-suspended, authorized user, who logged-in during the last 180 days by project
insert into redcap_project_ownership (pid, username, email, firstname, lastname)
  select project_id, username, user_email, user_firstname, user_lastname from
    (select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname, max(rcui.user_lastlogin) as last_login
    from redcap_projects as rcp
    inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
    left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id)
    left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
    inner join redcap_user_information as rcui on (rcur.username = rcui.username)
    left join paid_creators as pc on (pc.username = rcui.username)
    left join redcap_project_ownership as rcpo on (rcp.project_id = rcpo.pid)
    where (rcpo.email is null or rcpo.email = "")
        and (rcpo.username is null or rcpo.username = "")
        and rcui.user_suspended_time is null
        and datediff(now(), rcui.user_lastlogin) < 180
        and pc.username is null
    group by rcp.project_id) as input_columns;


-- Last_user is suspended: If owner is null and last_user is not in paid_creators, and last_user is suspended, then set owner to last_user
-- Last_user has some perms:
--   If owner is null and last_user is suspended and last_user is not in paid_creators, and last_user has some permissions on project, then set owner to last_user
-- set owner to last user with some perms
insert into redcap_project_ownership (pid, username, email, firstname, lastname)
    select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname
    from redcap_projects as rcp
    inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
    inner join redcap_user_information as rcui on (rcps.last_user = rcui.username)
    left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id and rcps.last_user = rcur.username)
    left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
    left join paid_creators as pc on (pc.username = rcui.username)
    left join redcap_project_ownership as rcpo on (rcp.project_id = rcpo.pid)
    where (rcpo.email is null or rcpo.email = "")
        and (rcpo.username is null or rcpo.username = "")
        and pc.username is null
        and rcps.last_user is not null;


-- set owner to the most recently logged-in, suspended, authorized user
-- set owner to the most recently logged suspended, authorized user by project
insert into redcap_project_ownership (pid, username, email, firstname, lastname)
  select project_id, username, user_email, user_firstname, user_lastname from
    (select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname, max(rcui.user_lastlogin) as last_login
    from redcap_projects as rcp
    inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
    left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id)
    left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
    inner join redcap_user_information as rcui on (rcur.username = rcui.username)
    left join paid_creators as pc on (pc.username = rcui.username)
    left join redcap_project_ownership as rcpo on (rcp.project_id = rcpo.pid)
    where (rcpo.email is null or rcpo.email = "")
        and (rcpo.username is null or rcpo.username = "")
        and pc.username is null
    group by rcp.project_id) as input_columns;


-- Creator, but suspended: If owner is null and creator is not in paid_creators, then set owner to creator
-- Set owner to creator
insert into redcap_project_ownership (pid, username, email, firstname, lastname)
    select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname
    from redcap_projects as rcp
      inner join redcap_user_information as rcui on (rcp.created_by = rcui.ui_id)
      left join redcap_project_ownership as rcpo on (rcp.project_id = rcpo.pid)
      left join paid_creators as pc on (pc.username = rcui.username)
    where (rcpo.email is null or rcpo.email = "")
      and (rcpo.username is null or rcpo.username = "")
      and pc.username is null;


-- Paid Creator: If owner is null and creator is not suspended and creator is in paid_creators, then set owner to creator
-- Set owner to creator
insert into redcap_project_ownership (pid, username, email, firstname, lastname)
    select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname
    from redcap_projects as rcp
      inner join redcap_user_information as rcui on (rcp.created_by = rcui.ui_id)
      left join redcap_project_ownership as rcpo on (rcp.project_id = rcpo.pid)
      left join paid_creators as pc on (pc.username = rcui.username)
    where (rcpo.email is null or rcpo.email = "")
      and (rcpo.username is null or rcpo.username = "");

-- Replace old creator with modern owner
update redcap_project_ownership set
username= "tls",
email = "tls@ufl.edu",
firstname= "Taryn",
lastname= "Stoffs"
where redcap_project_ownership.username = "swehmeyer";

-- Replace old creator with modern owner
update redcap_project_ownership set
username= "c.holman",
email = "c.holman",
firstname= "Corinne",
lastname= "Holman"
where redcap_project_ownership.username = "cabernat";

-- set ownership manually
insert into redcap_project_ownership (pid, username, email, firstname, lastname)
values
(101, "sattam", "maryam.sattari@medicine.ufl.edu", "Maryam", "Sattari");

insert into redcap_project_ownership (pid, username, email, firstname, lastname)
values
(624, "sgilbert", "sgilbert@ufl.edu", "Scott", "Gilbert");


-- set username where it is blank and the email address matches that of a REDCap user
-- set usernames where possible
-- Write data into a temporary table
DROP table if exists rcpo_temp;
CREATE TABLE rcpo_temp (
    pid INT NOT NULL,
    username VARCHAR(128),
    PRIMARY KEY (pid)
) collate utf8_unicode_ci;

truncate rcpo_temp;

insert into rcpo_temp (pid, username)
select rcpo.pid, rcui.username
from redcap_project_ownership as rcpo
left join redcap_user_information as rcui on (rcpo.email = rcui.user_email)
where (rcpo.username is null or rcpo.username = '')
and rcui.username is not null;

-- update the RCPO table
update redcap_project_ownership as rcpo
set rcpo.username = (select rcpot.username from rcpo_temp as rcpot
where rcpo.pid = rcpot.pid)
where rcpo.username is null
or rcpo.username = ""
;

-- Erase redundant contact info in RCPO table
update redcap_project_ownership as rcpo
set rcpo.email = NULL, rcpo.firstname = NULL, rcpo.lastname = NULL
where rcpo.username is not null and rcpo.username != "";
