<?php
define('CLI_SCRIPT', true);
define('ABORT_AFTER_CONFIG', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8054' || $CFG->dbname !== 'close19' || $CFG->prefix !== 'mdl_'
        || $CFG->phpunit_dbname !== 'close19_unit' || $CFG->phpunit_dbuser !== 'close19_unit'
        || $CFG->phpunit_prefix !== 'unit_' || $CFG->phpunit_dataroot !== '/var/www/phpunitdata'
        || realpath($CFG->phpunit_dataroot) === realpath($CFG->dataroot)) {
    throw new RuntimeException('Unsafe PHPUnit configuration.');
}
$db = new mysqli($CFG->dbhost, $CFG->phpunit_dbuser, $CFG->phpunit_dbpass, $CFG->phpunit_dbname);
foreach ($db->query('SHOW GRANTS FOR CURRENT_USER()')->fetch_all() as $grant) {
    if (str_contains($grant[0], 'ON *.*') && !str_contains($grant[0], 'GRANT USAGE ON')) { exit(20); }
    if (!str_contains($grant[0], 'ON *.*') && !str_contains($grant[0], '`close19_unit`.*')) { exit(21); }
}
try { $db->query('SELECT id FROM close19.mdl_course LIMIT 1'); exit(22); }
catch (mysqli_sql_exception $e) { if ($e->getCode() !== 1142) { exit(23); } }
echo 'ISOLATION_PASS: separate server, DB, restricted account, prefix and dataroot; mdl_course read denied' . PHP_EOL;
