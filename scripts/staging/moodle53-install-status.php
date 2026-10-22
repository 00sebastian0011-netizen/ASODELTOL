<?php
// Checks an empty/new staging database without bootstrapping Moodle.

declare(strict_types=1);

$password = trim((string) file_get_contents('/run/secrets/db_password'));
if ($password === '') {
    fwrite(STDERR, "Missing local database secret.\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$database = new mysqli('database', 'moodle', $password, 'moodle');
unset($password);
$result = $database->query(
    "SELECT COUNT(*) AS tablecount
       FROM information_schema.tables
      WHERE table_schema = 'moodle' AND table_name = 'mdl_config'"
);
$row = $result->fetch_assoc();
$database->close();

echo ((int) $row['tablecount'] === 1) ? "PRESENT\n" : "ABSENT\n";
