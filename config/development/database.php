<?php

$db['mysql']['hostname'] = 'localhost';
$db['mysql']['username'] = 'user';
$db['mysql']['password'] = 'password';
$db['mysql']['database'] = 'database_name';
$db['mysql']['dbdriver'] = 'mysqli';
$db['mysql']['char_set'] = 'utf8';
$db['mysql']['dbcollat'] = 'utf8_unicode_ci';

$tnsNames = '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))(CONNECT_DATA=(SERVER=dedicated)(SERVICE_NAME=testing.myserver.com.ar)))';

$db['oracle']['hostname'] = $tnsNames;
$db['oracle']['username'] = "user";
$db['oracle']['password'] = "password";
$db['oracle']['char_set'] = 'utf8';
