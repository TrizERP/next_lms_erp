<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Real connections for the Talent Management integrations: which connector an integration uses, the
 * encrypted token / SMTP settings it was connected with, and the account it is connected as.
 *
 * Also resets the demo rows that were seeded as "Connected" without any real connection behind them.
 *
 *   php artisan migrate --path=database/migrations/2026_11_03_150000_add_connection_columns_to_talent_admin_integrations.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talent_admin_integrations')) {
            return;
        }

        Schema::table('talent_admin_integrations', function (Blueprint $table) {
            if (! Schema::hasColumn('talent_admin_integrations', 'provider')) {
                $table->string('provider', 30)->nullable()->after('category'); // google | microsoft | linkedin | docusign | smtp | builtin | null
            }
            if (! Schema::hasColumn('talent_admin_integrations', 'connected_account')) {
                $table->string('connected_account', 191)->nullable()->after('status');
            }
            if (! Schema::hasColumn('talent_admin_integrations', 'credentials')) {
                $table->longText('credentials')->nullable()->after('connected_account'); // Crypt::encryptString(json)
            }
            if (! Schema::hasColumn('talent_admin_integrations', 'token_expires_at')) {
                $table->timestamp('token_expires_at')->nullable()->after('credentials');
            }
        });

        $providers = [
            'Google Calendar' => 'google', 'Microsoft Outlook Calendar' => 'microsoft', 'LinkedIn Jobs' => 'linkedin',
            'DocuSign' => 'docusign', 'SMTP Email' => 'smtp', 'ERP Staff Directory' => 'builtin',
        ];
        foreach ($providers as $name => $provider) {
            DB::table('talent_admin_integrations')->where('name', $name)->update(['provider' => $provider]);
        }

        // Demo rows claimed a connection that never happened. Only the built-in ERP directory is genuinely connected.
        DB::table('talent_admin_integrations')->where('description', 'like', '[demo]%')
            ->where(fn ($q) => $q->whereNull('provider')->orWhere('provider', '!=', 'builtin'))
            ->update(['status' => 'Disconnected', 'last_sync_at' => null, 'last_sync_note' => null, 'connected_account' => null, 'credentials' => null]);
        DB::table('talent_admin_integrations')->where('provider', 'builtin')->update(['status' => 'Connected', 'last_sync_note' => 'Built in - reads staff straight from the ERP']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('talent_admin_integrations')) {
            return;
        }

        Schema::table('talent_admin_integrations', function (Blueprint $table) {
            foreach (['provider', 'connected_account', 'credentials', 'token_expires_at'] as $column) {
                if (Schema::hasColumn('talent_admin_integrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
