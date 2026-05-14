#!/bin/bash
set -e

ROOT_PASS="$(cat /run/secrets/db_root_pass)"
ADMIN_PASS="$(cat /run/secrets/db_admin_pass)"
DB="${MYSQL_DATABASE:-portal_propriedade}"

mysql -uroot -p"${ROOT_PASS}" "${DB}" <<SQL
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'snguard'@'%';

GRANT SELECT, INSERT, UPDATE         ON ${DB}.users                  TO 'snguard'@'%';
GRANT SELECT, INSERT, UPDATE         ON ${DB}.objects                TO 'snguard'@'%';
GRANT SELECT, INSERT, UPDATE         ON ${DB}.contact_messages       TO 'snguard'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON ${DB}.tokens                 TO 'snguard'@'%';
GRANT          INSERT                ON ${DB}.lgpd_consent           TO 'snguard'@'%';
GRANT          INSERT                ON ${DB}.lgpd_deletion_requests TO 'snguard'@'%';
GRANT SELECT, INSERT                 ON ${DB}.rate_limits            TO 'snguard'@'%';
GRANT          INSERT                ON ${DB}.action_logs            TO 'snguard'@'%';
GRANT SELECT                         ON ${DB}.v_objects_public       TO 'snguard'@'%';
GRANT SELECT                         ON ${DB}.v_user_is_admin        TO 'snguard'@'%';

CREATE USER IF NOT EXISTS 'snguard_admin'@'%' IDENTIFIED BY '${ADMIN_PASS}';
ALTER  USER                'snguard_admin'@'%' IDENTIFIED BY '${ADMIN_PASS}';
GRANT SELECT, INSERT, UPDATE, DELETE ON ${DB}.* TO 'snguard_admin'@'%';

FLUSH PRIVILEGES;
SQL
