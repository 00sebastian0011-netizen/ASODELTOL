<?php
// Restores only a checkpoint created by backup.php; it accepts no paths or commands.
define('CLI_SCRIPT', true);
$id = $argv[1] ?? '';
if (!preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $id)) { throw new RuntimeException('invalid_backup_id'); }
$site = getenv('ASODELTOL_SITE_ROOT'); $state = getenv('ASODELTOL_OPS_STATE_DIR'); $moodledata = getenv('ASODELTOL_MOODLEDATA');
if (!$site || !$state || !$moodledata || !is_dir($moodledata) || !is_file($site . '/config.php')) { throw new RuntimeException('invalid_environment'); }
require($site . '/config.php');
$dir = rtrim($state, '/') . '/backups/' . $id; $manifest = json_decode(file_get_contents($dir . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (['database' => 'db_sha256', 'plugin' => 'plugin_sha256', 'moodledata' => 'moodledata_sha256'] as $file => $hash) if (empty($manifest[$file]) || empty($manifest[$hash]) || !hash_equals($manifest[$hash], hash_file('sha256', $dir . '/' . $manifest[$file]))) throw new RuntimeException('backup_integrity_failed');
$maintenance = $site . '/admin/cli/maintenance.php';
if (!is_file($maintenance)) { throw new RuntimeException('maintenance_cli_missing'); }
system(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($maintenance) . ' --enable', $maintenancecode);
if ($maintenancecode !== 0) { throw new RuntimeException('maintenance_enable_failed'); }
try {
$defaults = tempnam($dir, 'mysql-'); file_put_contents($defaults, "[client]\nhost={$CFG->dbhost}\nuser={$CFG->dbuser}\npassword={$CFG->dbpass}\n", LOCK_EX); chmod($defaults, 0600);
$restore = '/bin/gzip -dc ' . escapeshellarg($dir . '/' . $manifest['database']) . ' | /usr/bin/mysql --defaults-extra-file=' . escapeshellarg($defaults) . ' ' . escapeshellarg($CFG->dbname);
system($restore, $code); @unlink($defaults); if ($code !== 0) throw new RuntimeException('database_restore_failed');
$target = $site . '/public/local'; system('/bin/tar -C ' . escapeshellarg($target) . ' -xzf ' . escapeshellarg($dir . '/' . $manifest['plugin']), $code); if ($code !== 0) throw new RuntimeException('plugin_restore_failed');
$dataparent = dirname($moodledata); $datafile = $dir . '/' . $manifest['moodledata'];
system('/bin/tar -tzf ' . escapeshellarg($datafile) . ' | /usr/bin/head -n 1 | /usr/bin/grep -Fx ' . escapeshellarg(basename($moodledata) . '/'), $code); if ($code !== 0) throw new RuntimeException('moodledata_archive_invalid');
system('/usr/bin/find ' . escapeshellarg($moodledata) . ' -mindepth 1 -maxdepth 1 -exec /bin/rm -rf -- {} +', $code); if ($code !== 0) throw new RuntimeException('moodledata_clear_failed');
system('/bin/tar -C ' . escapeshellarg($dataparent) . ' -xzf ' . escapeshellarg($datafile), $code); if ($code !== 0) throw new RuntimeException('moodledata_restore_failed');
purge_all_caches(); echo json_encode(['status' => 'rolled_back', 'backup_id' => $id], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    system(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($maintenance) . ' --disable');
}
