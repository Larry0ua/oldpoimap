<?php
error_reporting(E_ALL);
define('DB_USER', 'poiuser');
define('DB_PASS', 'poiuser');
define('DB_NAME', 'pois');
define('MAX_SQ', 0.05);
function db_connect() {
    return new PDO('pgsql:dbname='.DB_NAME.';host=localhost;port=5432', DB_USER, DB_PASS);
}
