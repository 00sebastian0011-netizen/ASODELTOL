<?php
/** Assert the database result of the two parallel identity workers. */
define('CLI_SCRIPT', true);
require dirname(__DIR__, 2) . '/qa-moodle/config.php';
$ids = json_decode(file_get_contents(dirname(__DIR__, 2) . '/qa-ids.json'), true, 512, JSON_THROW_ON_ERROR);
[, $hash] = \local_asodeltolstaff\identity_registry::fingerprint('CC', 'QA-CONCURRENT-0001');
$count = $DB->count_records('local_asstaff_identity', ['doctype' => 'CC', 'dochash' => $hash]);
$outputs = [trim(file_get_contents($argv[1])), trim(file_get_contents($argv[2]))];
sort($outputs);
if ($count !== 1 || $outputs !== ['REJECT', 'WIN']) {
    throw new RuntimeException('Concurrency invariant failed: count=' . $count . ' outputs=' . json_encode($outputs));
}
echo "PASS: REQ-16-03 concurrent duplicate has exactly one winner and one rejection.\n";
