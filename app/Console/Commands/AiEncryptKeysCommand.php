<?php

namespace App\Console\Commands;

use App\Support\KeyVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypts the provider API keys that were saved before encryption at rest existed.
 *
 *   php artisan ai:encrypt-keys --dry-run     counts what would change, writes nothing
 *   php artisan ai:encrypt-keys               encrypts every plain-text key (idempotent)
 *   php artisan ai:encrypt-keys --decrypt     reverses it, e.g. before changing APP_KEY
 *
 * Encrypted values depend on APP_KEY. Run --decrypt before rotating APP_KEY and encrypt again after,
 * or the stored keys cannot be read. Take a database backup first.
 */
class AiEncryptKeysCommand extends Command
{
    protected $signature = 'ai:encrypt-keys {--dry-run : Report only, change nothing} {--decrypt : Turn encrypted keys back into plain text}';

    protected $description = 'Encrypt (or with --decrypt, decrypt) the API keys stored in ai_api_keys';

    public function handle(): int
    {
        if (! Schema::hasTable('ai_api_keys') || ! Schema::hasColumn('ai_api_keys', 'api_key')) {
            $this->error('ai_api_keys does not exist on this installation.');

            return self::FAILURE;
        }

        $decrypt = (bool) $this->option('decrypt');
        $dry = (bool) $this->option('dry-run');
        $changed = 0;
        $skipped = 0;
        $unreadable = 0;

        DB::table('ai_api_keys')->select('id', 'api_key')->orderBy('id')->chunkById(200, function ($rows) use ($decrypt, $dry, &$changed, &$skipped, &$unreadable) {
            foreach ($rows as $row) {
                $value = (string) $row->api_key;
                if ($value === '' || $value === '-') {
                    $skipped++;
                    continue;
                }

                if ($decrypt) {
                    if (! KeyVault::isSealed($value)) {
                        $skipped++;
                        continue;
                    }
                    $plain = KeyVault::reveal($value);
                    if ($plain === '' || $plain === null) {
                        $unreadable++;
                        continue;
                    }
                    $new = $plain;
                } else {
                    if (KeyVault::isSealed($value)) {
                        $skipped++;
                        continue;
                    }
                    $new = KeyVault::seal($value);
                }

                if (! $dry) {
                    DB::table('ai_api_keys')->where('id', $row->id)->update(['api_key' => $new]);
                }
                $changed++;
            }
        });

        $this->info(sprintf(
            '%s%d key(s) %s, %d left as they were%s.',
            $dry ? '[dry run] ' : '',
            $changed,
            $decrypt ? 'decrypted' : 'encrypted',
            $skipped,
            $unreadable ? ", {$unreadable} could not be decrypted (APP_KEY changed?)" : ''
        ));

        return $unreadable ? self::FAILURE : self::SUCCESS;
    }
}
