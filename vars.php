<?php
/*
 *
 * This file will be used by the register and verify scripts to set global variables.
 * This is mainly to protect server names, users, passwords, etc.
 *
 */

/* MySQL settings */
define('DB_NAME', 'DBNAME');
define('DB_USER', 'DBUSER');
define('DB_PASSWORD', 'PASSWORD');
define('DB_HOST', 'DBHOST');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

/** LDAP Information */
define('LDAP_HOST', 'ldap.somesite.com');
define('LDAP_USER', 'cn=ldapadmin,dc=somesite,dc=com');
define('LDAP_USER_BASE', 'ou=people,dc=somesite,dc=com');
define('LDAP_PASS', 'LDAPPASS');

//** General Information */
define('NOTIFY', 'support@somesite.com');
define('WEB_FRONT', 'www.somesite.com');
?>
