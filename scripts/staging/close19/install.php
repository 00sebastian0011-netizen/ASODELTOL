<?php
// This file is copied to /var/www/html/admin/cli in the new private runtime only.
$argv = [__FILE__, '--agree-license', '--lang=en', '--adminuser=qa_admin',
    '--adminpass=' . trim(file_get_contents('/run/secrets/admin_password')),
    '--adminemail=qa-admin@example.invalid', '--fullname=ASODELTOL synthetic closure19',
    '--shortname=ASODELTOL-QA19'];
$_SERVER['argv'] = $argv;
require(__DIR__ . '/install_database.php');
