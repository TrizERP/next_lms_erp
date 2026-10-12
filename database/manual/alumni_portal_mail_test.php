<?php

/**
 * Sends ONE plain test email through the application's configured mail transport, to a recipient you name. Run it only when you
 * mean to: it really sends.
 *
 *   php database/manual/alumni_portal_mail_test.php you@your-domain.example
 *
 * It refuses the `log` and `array` drivers (they would pretend), refuses to run without a recipient, and prints the transport
 * name and the From domain but never a credential. The body is plain and contains no link or token. After it, check the inbox:
 *   - the message arrived (not in spam), and
 *   - the From shown is the address you configured (a provider that does not own the From address rewrites it).
 * Then do the same with the real flows on a staging copy: an invitation, an email confirmation and a password reset.
 */
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$to = $argv[1] ?? '';
if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php database/manual/alumni_portal_mail_test.php recipient@example.org\n");
    exit(2);
}
$mailer = (string) config('mail.default');
if (in_array($mailer, ['log', 'array'], true)) {
    fwrite(STDERR, "REFUSING: the '{$mailer}' mail driver does not deliver anything.\n");
    exit(1);
}
$from = (string) config('mail.from.address');
echo "Transport: {$mailer}; From domain: ", substr((string) strrchr($from, '@'), 1), "\n";
try {
    Illuminate\Support\Facades\Mail::raw("This is a test message from the alumni portal release checks.\n\nIf you can read this, outgoing email works. Nothing needs to be done.", function ($m) use ($to) {
        $m->to($to)->subject('Alumni portal mail test');
    });
    echo "Handed to the mail server without error. Now confirm it arrived and check the From address.\n";
} catch (Throwable $e) {
    echo 'FAILED: ', get_class($e), "\n";
    exit(1);
}
