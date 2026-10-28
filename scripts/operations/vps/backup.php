<?php
// Creates an atomic, private code+database checkpoint without emitting credentials.
define('CLI_SCRIPT', true);
$label = $argv[1] ?? 'manual';
if (!preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $label)) { throw new RuntimeException('invalid_label'); }
$site = getenv('ASODELTOL_SITE_ROOT'); $state = getenv('ASODELTOL_OPS_STATE_DIR'); $moodledata = getenv('ASODELTOL_MOODLEDATA');
if (!$site || !$state || !$moodledata || !is_dir($moodledata) || !is_file($site . '/config.php')) { throw new RuntimeException('invalid_environment'); }
require($site . '/config.php');
$id = gmdate('Ymd\THis\Z') . '-' . $label;
$dir = rtrim($state, '/') . '/backups/' . $id;
if (!mkdir($dir, 0700, true)) { throw new RuntimeException('backup_directory_failed'); }
$maintenance = $site . '/admin/cli/maintenance.php';
if (!is_file($maintenance)) { throw new RuntimeException('maintenance_cli_missing'); }
system(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($maintenance) . ' --enable', $maintenancecode);
if ($maintenancecode !== 0) { throw new RuntimeException('maintenance_enable_failed'); }
try {
$defaults = tempnam($dir, 'mysql-');
file_put_contents($defaults, "[client]\nhost={$CFG->dbhost}\nuser={$CFG->dbuser}\npassword={$CFG->dbpass}\n", LOCK_EX); chmod($defaults, 0600);
$dump = $dir . '/moodle.sql.gz';
$command = ['/usr/bin/mysqldump', '--defaults-extra-file=' . $defaults, '--single-transaction', '--routines', '--events', $CFG->dbname];
$pipes = []; $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$gzip = gzopen($dump, 'wb9');
if (!is_resource($process) || !$gzip) { @unlink($defaults); throw new RuntimeException('database_backup_failed'); }
while (!feof($pipes[1])) { $chunk = fread($pipes[1], 1048576); if ($chunk !== false) { gzwrite($gzip, $chunk); } }
fclose($pipes[1]); fclose($pipes[2]); gzclose($gzip);
if (proc_close($process) !== 0) { @unlink($defaults); throw new RuntimeException('database_backup_failed'); }
@unlink($defaults);
$plugin = $site . '/public/local/asodeltolstaff'; $archive = $dir . '/asodeltolstaff.tar.gz';
$code = 0; system('/bin/tar -C ' . escapeshellarg(dirname($plugin)) . ' -czf ' . escapeshellarg($archive) . ' ' . escapeshellarg(basename($plugin)), $code);
if ($code !== 0) { throw new RuntimeException('plugin_backup_failed'); }
$dataarchive = $dir . '/moodledata.tar.gz';
system('/bin/tar -C ' . escapeshellarg(dirname($moodledata)) . ' --exclude=' . escapeshellarg(basename($moodledata) . '/cache') . ' --exclude=' . escapeshellarg(basename($moodledata) . '/localcache') . ' --exclude=' . escapeshellarg(basename($moodledata) . '/sessions') . ' --exclude=' . escapeshellarg(basename($moodledata) . '/temp') . ' -czf ' . escapeshellarg($dataarchive) . ' ' . escapeshellarg(basename($moodledata)), $code);
if ($code !== 0) { throw new RuntimeException('moodledata_backup_failed'); }
$manifest = ['id' => $id, 'created_at' => gmdate('c'), 'database' => basename($dump), 'plugin' => basename($archive), 'moodledata' => basename($dataarchive), 'db_sha256' => hash_file('sha256', $dump), 'plugin_sha256' => hash_file('sha256', $archive), 'moodledata_sha256' => hash_file('sha256', $dataarchive)];
file_put_contents($dir . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES), LOCK_EX);
} finally {
    system(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($maintenance) . ' --disable');
}
echo json_encode(['status' => 'backed_up', 'backup_id' => $id], JSON_UNESCAPED_UNICODE) . PHP_EOL;
