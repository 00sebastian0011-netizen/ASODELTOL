<?php
/** Read back one synthetic entry ID after browser submission. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8054' || $CFG->dbname !== 'close19') {
    throw new RuntimeException('Isolated staging required');
}
$concept = $argv[1] ?? '';
if (!str_starts_with($concept, 'QA350 tutor glosario ')) {
    throw new InvalidArgumentException('Unexpected synthetic concept');
}
$sql = 'SELECT ge.id FROM {glossary_entries} ge
          JOIN {glossary} g ON g.id = ge.glossaryid
          JOIN {course} c ON c.id = g.course
         WHERE ge.concept = :concept AND c.shortname = :shortname';
$records = $DB->get_records_sql($sql, ['concept' => $concept, 'shortname' => 'QA350-AF8']);
if (count($records) !== 1) {
    throw new RuntimeException('Expected exactly one persisted entry');
}
echo (int) array_key_first($records);
