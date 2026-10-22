<?php
// Passes the generated local secret to Moodle's official database installer without exposing it to the host CLI.

declare(strict_types=1);

$password = trim((string) file_get_contents('/run/secrets/admin_password'));
if ($password === '') {
    fwrite(STDERR, "Missing local administrator secret.\n");
    exit(1);
}

$argv = [
    '/var/www/html/admin/cli/install_database.php',
    '--agree-license',
    '--lang=en',
    '--adminuser=asodeltol_admin',
    '--adminpass=' . $password,
    '--adminemail=admin@example.invalid',
    '--fullname=ASODELTOL Moodle 5.3 Staging',
    '--shortname=ASODELTOL-STG',
    '--supportemail=support@example.invalid',
    '--noreplyemail=noreply@example.invalid',
];
$_SERVER['argv'] = $argv;
$_SERVER['argc'] = count($argv);
unset($password);

require('/var/www/html/admin/cli/install_database.php');
