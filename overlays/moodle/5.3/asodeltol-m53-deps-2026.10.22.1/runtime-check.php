<?php

declare(strict_types=1);

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');

use Composer\InstalledVersions;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;

/**
 * Security dependency and reachability smoke test for the isolated candidate.
 *
 * This file intentionally emits only versions, counts, and boolean outcomes.
 */
function package_version(string $package): string {
    return InstalledVersions::getPrettyVersion($package) ?? 'unknown';
}

$strongkey = str_repeat('k', 32);
$syntheticjwt = JWT::encode(['sub' => 'synthetic'], $strongkey, 'HS256');
$decoded = JWT::decode($syntheticjwt, new Key($strongkey, 'HS256'));

$weakkeyrejected = false;
try {
    JWT::encode(['sub' => 'synthetic'], 'short', 'HS256');
} catch (Throwable) {
    $weakkeyrejected = true;
}

$ambiguoushostrejected = false;
try {
    (new Uri('https://example.invalid/'))->withHost('good.example@evil.example');
} catch (Throwable) {
    $ambiguoushostrejected = true;
}

$noncanonicalhostrejected = false;
try {
    (new Client(['timeout' => 1]))->request('GET', 'http://127.0.0.%31/');
} catch (Throwable $error) {
    $noncanonicalhostrejected = str_contains($error->getMessage(), 'must not contain a percent escape');
}

$result = [
    'packages' => [
        'aws/aws-sdk-php' => package_version('aws/aws-sdk-php'),
        'firebase/php-jwt' => package_version('firebase/php-jwt'),
        'guzzlehttp/guzzle' => package_version('guzzlehttp/guzzle'),
        'guzzlehttp/promises' => package_version('guzzlehttp/promises'),
        'guzzlehttp/psr7' => package_version('guzzlehttp/psr7'),
        'mtdowling/jmespath.php' => package_version('mtdowling/jmespath.php'),
        'symfony/filesystem' => package_version('symfony/filesystem'),
        'symfony/polyfill-php80' => package_version('symfony/polyfill-php80'),
    ],
    'configured_routes' => [
        'ai_providers_total' => $DB->count_records('ai_providers'),
        'ai_providers_enabled' => $DB->count_records('ai_providers', ['enabled' => 1]),
        'awsbedrock_enabled' => $DB->count_records('ai_providers', [
            'provider' => 'aiprovider_awsbedrock',
            'enabled' => 1,
        ]),
        'lti_instances' => $DB->count_records('lti'),
        'lti_types' => $DB->count_records('lti_types'),
        'bigbluebutton_instances' => $DB->count_records('bigbluebuttonbn'),
        'sms_gateways_total' => $DB->count_records('sms_gateways'),
        'sms_gateways_enabled' => $DB->count_records('sms_gateways', ['enabled' => 1]),
    ],
    'security_smoke' => [
        'jmes_compile_constant_defined' => defined('JP_PHP_COMPILE'),
        'jmes_default_runtime_result' => JmesPath\search('value', ['value' => 'ok']),
        'jwt_strong_key_roundtrip' => ($decoded->sub ?? null) === 'synthetic',
        'jwt_weak_key_rejected' => $weakkeyrejected,
        'psr7_ambiguous_host_rejected' => $ambiguoushostrejected,
        'guzzle_noncanonical_host_rejected_before_io' => $noncanonicalhostrejected,
    ],
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
