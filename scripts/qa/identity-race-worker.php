<?php
/** One process in the REQ-16-03 real MySQL concurrency check. */
define('CLI_SCRIPT', true);
require dirname(__DIR__, 2) . '/qa-moodle/config.php';
if (getenv('ASODELTOL_QA_ONLY') !== 'isolated-gh-runner' || $CFG->dbname !== 'asodeltolqa') {
    throw new RuntimeException('Refusing worker outside isolated Moodle QA');
}
$userid = (int)($argv[1] ?? 0);
try {
    \local_asodeltolstaff\identity_registry::bind($userid, 'CC', 'QA-CONCURRENT-0001', 'create');
    echo "WIN\n";
} catch (Throwable $e) {
    echo "REJECT\n";
}
