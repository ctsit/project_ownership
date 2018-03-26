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
) collate utf8_unicode_ci;

truncate rcpo_test;

-- Fix collation in redcap_project_stats
alter table redcap_project_ownership
CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- PI:
-- If owner is null and PI data is not null, then set owner to PI.
-- list the rows where pi email, fn, and ln are set. (1705)
select project_id, project_pi_username, project_pi_email, project_pi_firstname, project_pi_lastname from redcap_projects where project_pi_email is not null and project_pi_email != "";
-- Look for rows where pi usernames is set (0)
select project_id, project_pi_username from redcap_projects where project_pi_username is not null and project_pi_username != "";
-- List the owners to be set
select project_id, project_pi_username, project_pi_email, project_pi_firstname, project_pi_lastname
  from redcap_projects as rcp left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
  where project_pi_email is not null and project_pi_email != ""
  and (rcpo.email is null or rcpo.email = "")
  and (rcpo.username is null or rcpo.username = "");
-- set the owner to the PI
insert into rcpo_test (pid, username, email, firstname, lastname)
  select project_id, project_pi_username, project_pi_email, project_pi_firstname, project_pi_lastname
  from redcap_projects as rcp left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
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
  and (rcpo.username is null or rcpo.username = "")
  and rcui.user_suspended_time is null
  and datediff(now(), rcui.user_lastlogin) < 180
  and pc.username is null;
-- Set owner to creator
insert into rcpo_test (pid, username, email, firstname, lastname)
    select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname
    from redcap_projects as rcp
      inner join redcap_user_information as rcui on (rcp.created_by = rcui.ui_id)
      left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
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
    and (rcpo.username is null or rcpo.username = "")
    and rcui.user_suspended_time is null
    and datediff(now(), rcui.user_lastlogin) < 180
    and pc.username is null
  and rcps.last_user is not null
  and (rcur.design = 1 or rcur.user_rights = 1 or rcuro.design = 1 or rcuro.user_rights = 1);

-- set owner to last unsuspended user with some perms who logged-in during the last 180 days
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
        and (rcpo.username is null or rcpo.username = "")
        and rcui.user_suspended_time is null
        and datediff(now(), rcui.user_lastlogin) < 180
        and pc.username is null
        and rcps.last_user is not null
        and (rcur.design = 1 or rcur.user_rights = 1 or rcuro.design = 1 or rcuro.user_rights = 1);


-- Any authz’d user who is not suspended: If owner is null and list of non-suspended, authz’d users is not null, then set owner to a random entry from that list.
-- Enumerate non-suspended, authorized users by project
select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname, rcui.user_suspended_time
from redcap_projects as rcp
inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id)
left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
inner join redcap_user_information as rcui on (rcur.username = rcui.username)
left join paid_creators as pc on (pc.username = rcui.username)
left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
where (rcpo.email is null or rcpo.email = "")
    and (rcpo.username is null or rcpo.username = "")
    and rcui.user_suspended_time is null
    and datediff(now(), rcui.user_lastlogin) < 180
    and pc.username is null
order by rcp.project_id;

-- count authz'd, non-suspended users by project
select rcp.project_id, count(*) as qty
from redcap_projects as rcp
inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id)
left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
inner join redcap_user_information as rcui on (rcur.username = rcui.username)
left join paid_creators as pc on (pc.username = rcui.username)
left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
where (rcpo.email is null or rcpo.email = "")
    and (rcpo.username is null or rcpo.username = "")
    and rcui.user_suspended_time is null
    and datediff(now(), rcui.user_lastlogin) < 180
    and pc.username is null
group by rcp.project_id
order by qty desc;


-- enumerate the projects with only 1 authz'd, non-suspended user
select rcp.project_id, count(*) as qty
from redcap_projects as rcp
inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id)
left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
inner join redcap_user_information as rcui on (rcur.username = rcui.username)
left join paid_creators as pc on (pc.username = rcui.username)
left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
where (rcpo.email is null or rcpo.email = "")
    and (rcpo.username is null or rcpo.username = "")
    and rcui.user_suspended_time is null
    and datediff(now(), rcui.user_lastlogin) < 180
    and pc.username is null
group by rcp.project_id
having qty = 1
order by qty desc;

-- Return the most recently logged non-suspended, authorized user by project
select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname, max(rcui.user_lastlogin) as last_login
from redcap_projects as rcp
inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id)
left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
inner join redcap_user_information as rcui on (rcur.username = rcui.username)
left join paid_creators as pc on (pc.username = rcui.username)
left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
where (rcpo.email is null or rcpo.email = "")
    and (rcpo.username is null or rcpo.username = "")
    and rcui.user_suspended_time is null
    and datediff(now(), rcui.user_lastlogin) < 180
    and pc.username is null
group by rcp.project_id;

-- set owner to the most recently logged non-suspended, authorized user, who logged-in during the last 180 days by project
insert into rcpo_test (pid, username, email, firstname, lastname)
  select project_id, username, user_email, user_firstname, user_lastname from
    (select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname, max(rcui.user_lastlogin) as last_login
    from redcap_projects as rcp
    inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
    left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id)
    left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
    inner join redcap_user_information as rcui on (rcur.username = rcui.username)
    left join paid_creators as pc on (pc.username = rcui.username)
    left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
    where (rcpo.email is null or rcpo.email = "")
        and (rcpo.username is null or rcpo.username = "")
        and rcui.user_suspended_time is null
        and datediff(now(), rcui.user_lastlogin) < 180
        and pc.username is null
    group by rcp.project_id) as input_columns;


-- Last_user is suspended: If owner is null and last_user is not in paid_creators, and last_user is suspended, then set owner to last_user
-- Last_user has some perms:
--   If owner is null and last_user is suspended and last_user is not in paid_creators, and last_user has some permissions on project, then set owner to last_user
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
  and (rcpo.username is null or rcpo.username = "")
  and pc.username is null
  and rcps.last_user is not null;

-- set owner to last user with some perms
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
        and (rcpo.username is null or rcpo.username = "")
        and pc.username is null
        and rcps.last_user is not null;


-- set owner to the most recently logged-in, suspended, authorized user
-- Return the most recently logged suspended, authorized user by project
select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname, max(rcui.user_lastlogin) as last_login
from redcap_projects as rcp
inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id)
left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
inner join redcap_user_information as rcui on (rcur.username = rcui.username)
left join paid_creators as pc on (pc.username = rcui.username)
left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
where (rcpo.email is null or rcpo.email = "")
    and (rcpo.username is null or rcpo.username = "")
    and pc.username is null
group by rcp.project_id;

-- set owner to the most recently logged suspended, authorized user by project
insert into rcpo_test (pid, username, email, firstname, lastname)
  select project_id, username, user_email, user_firstname, user_lastname from
    (select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname, max(rcui.user_lastlogin) as last_login
    from redcap_projects as rcp
    inner join redcap_project_stats as rcps on (rcp.project_id = rcps.project_id)
    left join redcap_user_rights as rcur on (rcp.project_id = rcur.project_id)
    left join redcap_user_roles as rcuro on (rcp.project_id = rcuro.project_id and rcur.role_id = rcuro.role_id)
    inner join redcap_user_information as rcui on (rcur.username = rcui.username)
    left join paid_creators as pc on (pc.username = rcui.username)
    left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
    where (rcpo.email is null or rcpo.email = "")
        and (rcpo.username is null or rcpo.username = "")
        and pc.username is null
    group by rcp.project_id) as input_columns;


-- Creator, but suspended: If owner is null and creator is not in paid_creators, then set owner to creator
-- List owners to be set
select rcp.project_id, rcui.username
from redcap_projects as rcp
  inner join redcap_user_information as rcui on (rcp.created_by = rcui.ui_id)
  left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
  left join paid_creators as pc on (pc.username = rcui.username)
where (rcpo.email is null or rcpo.email = "")
  and (rcpo.username is null or rcpo.username = "")
  and pc.username is null;
-- Set owner to creator
insert into rcpo_test (pid, username, email, firstname, lastname)
    select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname
    from redcap_projects as rcp
      inner join redcap_user_information as rcui on (rcp.created_by = rcui.ui_id)
      left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
      left join paid_creators as pc on (pc.username = rcui.username)
    where (rcpo.email is null or rcpo.email = "")
      and (rcpo.username is null or rcpo.username = "")
      and pc.username is null;


-- Paid Creator: If owner is null and creator is not suspended and creator is in paid_creators, then set owner to creator
-- List owners to be set
select rcp.project_id, rcui.username
from redcap_projects as rcp
  inner join redcap_user_information as rcui on (rcp.created_by = rcui.ui_id)
  left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
  left join paid_creators as pc on (pc.username = rcui.username)
where (rcpo.email is null or rcpo.email = "")
  and (rcpo.username is null or rcpo.username = "");

-- group by purpose
select rcp.purpose, count(*) as qty
from redcap_projects as rcp
  inner join redcap_user_information as rcui on (rcp.created_by = rcui.ui_id)
  left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
  left join paid_creators as pc on (pc.username = rcui.username)
where (rcpo.email is null or rcpo.email = "")
  and (rcpo.username is null or rcpo.username = "")
group by rcp.purpose
order by qty desc;

-- Set owner to creator
insert into rcpo_test (pid, username, email, firstname, lastname)
    select rcp.project_id, rcui.username, rcui.user_email, rcui.user_firstname, rcui.user_lastname
    from redcap_projects as rcp
      inner join redcap_user_information as rcui on (rcp.created_by = rcui.ui_id)
      left join rcpo_test as rcpo on (rcp.project_id = rcpo.pid)
      left join paid_creators as pc on (pc.username = rcui.username)
    where (rcpo.email is null or rcpo.email = "")
      and (rcpo.username is null or rcpo.username = "");

-- Replace old creator with modern owner
update rcpo_test set
username= "tls",
email = "tls@ufl.edu",
firstname= "Taryn",
lastname= "Stoffs"
where rcpo_test.username = "swehmeyer";

-- Replace old creator with modern owner
update rcpo_test set
username= "c.holman",
email = "c.holman",
firstname= "Corinne",
lastname= "Holman"
where rcpo_test.username = "cabernat";

-- set ownership manually
insert into rcpo_test (pid, username, email, firstname, lastname)
values
(101, "sattam", "maryam.sattari@medicine.ufl.edu", "Maryam", "Sattari");

insert into rcpo_test (pid, username, email, firstname, lastname)
values
(624, "sgilbert", "sgilbert@ufl.edu", "Scott", "Gilbert");


-- Fix collation in rcpo_test
alter table rcpo_test
CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;


-- set username where it is blank and the email address matches that of a REDCap user
-- enumerate rows with issues and solutions
select rcpo.pid, rcpo.username, rcpo.email, rcui.user_email, rcui.username
from rcpo_test as rcpo
left join redcap_user_information as rcui on (rcpo.email = rcui.user_email)
where (rcpo.username is null or rcpo.username = '')
and rcui.username is not null;

-- set usernames where possible
-- Write data into a temporary table
CREATE TABLE rcpo_temp (
    pid INT NOT NULL,
    username VARCHAR(128),
    PRIMARY KEY (pid)
) collate utf8_unicode_ci;

truncate rcpo_temp;

insert into rcpo_temp (pid, username)
select rcpo.pid, rcui.username
from rcpo_test as rcpo
left join redcap_user_information as rcui on (rcpo.email = rcui.user_email)
where (rcpo.username is null or rcpo.username = '')
and rcui.username is not null;

-- update the RCPO table
update rcpo_test as rcpo
set rcpo.username = (select rcpot.username from rcpo_temp as rcpot
where rcpo.pid = rcpot.pid)
where rcpo.username is null
or rcpo.username = ""
;

-- Erase redundant contact info in RCPO table
update rcpo_test as rcpo
set rcpo.email = NULL, rcpo.firstname = NULL, rcpo.lastname = NULL
where rcpo.username is not null and rcpo.username != "";

select * FROM RCPO_TEST;

select * from redcap_projects;
