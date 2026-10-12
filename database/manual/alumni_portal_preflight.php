<?php

/**
 * Alumni portal release pre-flight. READ-ONLY: it changes nothing and prints NO secret - only yes/no answers, lengths and the
 * non-secret parts of the configuration (mailer name, host name, domain of the From address).
 *
 *   php database/manual/alumni_portal_preflight.php
 *
 * Run it on the server that will serve the portal, with the environment the portal will really use. Exit code 0 only when every
 * REQUIRED check passes. It reports on whichever database the application is pointed at and names it first.
 */
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$results = [];
$check = function (string $name, bool $ok, string $detail = '', bool $required = true) use (&$results) {
    $results[] = [$name, $ok, $detail, $required];
};

$secret = (string) config('alumni_auth.jwt_secret');
$staff = (string) config('alumni_auth.staff_jwt_secret');
$url = (string) config('alumni_auth.portal_url');
$mailer = (string) config('mail.default');
$from = (string) config('mail.from.address');
$fromDomain = substr((string) strrchr($from, '@'), 1);

echo "# Alumni portal pre-flight\n\nDatabase: ", config('database.connections.mysql.host'), ' / ', DB::connection()->getDatabaseName(), "\nApp env: ", config('app.env'), "\n\n";

$check('ALUMNI_JWT_SECRET is set', $secret !== '');
$check('ALUMNI_JWT_SECRET is at least 32 characters', strlen($secret) >= 32, 'length ' . strlen($secret));
$check('ALUMNI_JWT_SECRET differs from the staff JWT_SECRET', $secret !== '' && ! hash_equals($staff, $secret));
$check('ALUMNI_PORTAL_URL is set', $url !== '');
$check('ALUMNI_PORTAL_URL uses https', str_starts_with($url, 'https://'), $url !== '' ? 'host ' . parse_url($url, PHP_URL_HOST) : '');
$check('ALUMNI_PORTAL_URL has no trailing slash and no path', $url !== '' && rtrim($url, '/') === $url && (parse_url($url, PHP_URL_PATH) === null || parse_url($url, PHP_URL_PATH) === ''));
$check('mail driver is a real transport (not log/array)', ! in_array($mailer, ['log', 'array'], true), 'mailer ' . $mailer);
$check('MAIL_FROM_ADDRESS is not a placeholder', $from !== '' && ! preg_match('/^(example\.(com|org|net)|.*\.invalid|.*\.test|localhost)$/i', $fromDomain), 'domain ' . ($fromDomain ?: '(none)'));
$smtpUser = (string) config('mail.mailers.smtp.username');
$check('MAIL_FROM_ADDRESS matches the authenticated SMTP account (or you have verified it as an alias)', $mailer !== 'smtp' || strcasecmp($smtpUser, $from) === 0, 'if this fails, confirm the From address is a verified alias at the mail provider', false);
$check('APP_DEBUG is off', ! config('app.debug'), 'debug pages can print configuration', false);
$check('APP_ENV is production', config('app.env') === 'production', 'env ' . config('app.env'), false);

foreach (['alumni_accounts', 'alumni_auth_tokens', 'alumni_auth_sessions'] as $t) {
    $check("table {$t} exists", Schema::hasTable($t));
}
$check('auth migration is recorded', DB::table('migrations')->where('migration', '2026_10_14_100000_create_alumni_auth_tables')->exists());
$check('Super Admin rights migration is recorded', DB::table('migrations')->where('migration', '2026_10_14_100100_grant_super_admin_donations_audit_menu')->exists(), '', false);

$failedRequired = 0;
foreach ($results as [$name, $ok, $detail, $required]) {
    echo ($ok ? '[PASS] ' : ($required ? '[FAIL] ' : '[WARN] ')), $name, $detail !== '' ? "  ({$detail})" : '', "\n";
    $failedRequired += (! $ok && $required) ? 1 : 0;
}
echo "\n", $failedRequired === 0 ? 'READY: every required check passes.' : "NOT READY: {$failedRequired} required check(s) failing.", "\n";
exit($failedRequired === 0 ? 0 : 1);
