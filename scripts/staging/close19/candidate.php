<?php
/** Hash candidate source only; does not include config.php or user files. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8054' || $CFG->dbname !== 'close19') {
    throw new RuntimeException('Independent fixture runtime required.');
}
$base = $CFG->dirroot . '/local/asodeltolstaff/';
$hashes = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile()) {
        $hashes[substr($file->getPathname(), strlen($base))] = hash_file('sha256', $file->getPathname());
    }
}
ksort($hashes);
$themebase = $CFG->dirroot . '/theme/rsmax/';
$themehashes = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($themebase, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile()) {
        $themehashes[substr($file->getPathname(), strlen($themebase))] = hash_file('sha256', $file->getPathname());
    }
}
ksort($themehashes);
echo json_encode(['version' => get_config('local_asodeltolstaff', 'version'), 'files' => $hashes,
    'theme_version' => get_config('theme_rsmax', 'version'), 'theme_files' => $themehashes], JSON_THROW_ON_ERROR);
