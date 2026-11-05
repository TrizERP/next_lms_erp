<?php

namespace App\Services\AI;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ConversationalSettingsService
{
    public const SETTING_KEY = 'conversational_ai_channel_settings';

    public const SUPPORTED_LANGUAGES = ['en-IN', 'hi-IN', 'gu-IN'];

    /**
     * The original, permanent platforms.
     */
    public const SUPPORTED_PLATFORMS = ['lms_k12', 'enterprise_brain', 'g2g'];

    /**
     * Default channel configuration per module.
     */
    public function defaultSettings(): array
    {
        $defaultChannel = [
            'voice_enabled' => true,
            'default_language' => 'en-IN',
            'show_ask_tab' => true,
            'show_create_tab' => true,
            'suggested_prompt_source' => 'workspace',
            'updated_at' => null,
            'updated_by' => null,
        ];

        $projects = [];
        foreach (self::SUPPORTED_PLATFORMS as $platform) {
            $projects[$platform] = array_merge($defaultChannel, ['project_id' => $platform]);
        }

        return [
            'platforms' => self::SUPPORTED_PLATFORMS,
            'projects' => $projects,
            'updated_at' => null,
            'updated_by' => null,
        ];
    }

    /**
     * Retrieve settings, module-specifically.
     * When $projectId is specified, returns that module's independent configuration.
     */
    public function getCentralizedSettings(?string $projectId = null): array
    {
        $defaults = $this->defaultSettings();

        try {
            $row = DB::table('ai_live_chat_settings')
                ->where('setting_key', self::SETTING_KEY)
                ->first();

            if ($row && !empty($row->setting_value)) {
                $decoded = json_decode($row->setting_value, true);
                if (is_array($decoded) && isset($decoded['projects']) && is_array($decoded['projects'])) {
                    foreach (self::SUPPORTED_PLATFORMS as $platform) {
                        if (isset($decoded['projects'][$platform]) && is_array($decoded['projects'][$platform])) {
                            $defaults['projects'][$platform] = array_merge(
                                $defaults['projects'][$platform],
                                $decoded['projects'][$platform]
                            );
                        }
                    }
                    if (isset($decoded['updated_at'])) {
                        $defaults['updated_at'] = $decoded['updated_at'];
                    }
                    if (isset($decoded['updated_by'])) {
                        $defaults['updated_by'] = $decoded['updated_by'];
                    }
                }
            }
        } catch (Throwable $e) {
            // Return defaults if database read fails
        }

        // Ensure removed platforms (brain, enterprise) are purged
        unset($defaults['projects']['brain'], $defaults['projects']['enterprise']);
        $defaults['platforms'] = self::SUPPORTED_PLATFORMS;

        // Ensure each supported platform has complete, valid fields
        foreach (self::SUPPORTED_PLATFORMS as $plat) {
            if (!isset($defaults['projects'][$plat]) || !is_array($defaults['projects'][$plat])) {
                $defaults['projects'][$plat] = [
                    'project_id' => $plat,
                    'voice_enabled' => true,
                    'default_language' => 'en-IN',
                    'show_ask_tab' => true,
                    'show_create_tab' => true,
                    'suggested_prompt_source' => 'workspace',
                    'updated_at' => null,
                    'updated_by' => null,
                ];
            } else {
                $defaults['projects'][$plat]['project_id'] = $plat;
                $defaults['projects'][$plat]['voice_enabled'] = (bool)($defaults['projects'][$plat]['voice_enabled'] ?? true);
                $defaults['projects'][$plat]['default_language'] = (string)($defaults['projects'][$plat]['default_language'] ?? 'en-IN');
            }
        }

        if ($projectId) {
            $normalizedId = strtolower(trim($projectId));
            return $defaults['projects'][$normalizedId] ?? [
                'project_id' => $normalizedId,
                'voice_enabled' => true,
                'default_language' => 'en-IN',
                'show_ask_tab' => true,
                'show_create_tab' => true,
                'suggested_prompt_source' => 'workspace',
                'updated_at' => null,
                'updated_by' => null,
            ];
        }

        return $defaults;
    }

    /**
     * Persist module-specific settings to the database.
     * Updates ONLY the target module without affecting other modules.
     */
    public function saveCentralizedSettings(array $input, ?string $updatedBy = null): array
    {
        $current = $this->getCentralizedSettings();
        $now = Carbon::now()->toIso8601String();

        $targetProject = isset($input['project_id']) ? strtolower(trim((string)$input['project_id'])) : 'lms_k12';

        if (!isset($current['projects'][$targetProject]) || !is_array($current['projects'][$targetProject])) {
            $current['projects'][$targetProject] = [
                'project_id' => $targetProject,
                'voice_enabled' => true,
                'default_language' => 'en-IN',
                'show_ask_tab' => true,
                'show_create_tab' => true,
                'suggested_prompt_source' => 'workspace',
                'updated_at' => null,
                'updated_by' => null,
            ];
        }

        // Apply changes ONLY to the target project
        if (array_key_exists('voice_enabled', $input)) {
            $current['projects'][$targetProject]['voice_enabled'] = (bool)$input['voice_enabled'];
        }

        if (array_key_exists('default_language', $input)) {
            $lang = trim((string)$input['default_language']);
            if (in_array($lang, self::SUPPORTED_LANGUAGES, true)) {
                $current['projects'][$targetProject]['default_language'] = $lang;
            }
        }

        if (array_key_exists('show_ask_tab', $input)) {
            $current['projects'][$targetProject]['show_ask_tab'] = (bool)$input['show_ask_tab'];
        }

        if (array_key_exists('show_create_tab', $input)) {
            $current['projects'][$targetProject]['show_create_tab'] = (bool)$input['show_create_tab'];
        }

        if (array_key_exists('suggested_prompt_source', $input)) {
            $current['projects'][$targetProject]['suggested_prompt_source'] = (string)$input['suggested_prompt_source'];
        }

        $current['projects'][$targetProject]['updated_at'] = $now;
        if ($updatedBy !== null) {
            $current['projects'][$targetProject]['updated_by'] = $updatedBy;
        }

        $current['updated_at'] = $now;
        if ($updatedBy !== null) {
            $current['updated_by'] = $updatedBy;
        }

        // Explicitly purge brain and enterprise
        unset($current['projects']['brain'], $current['projects']['enterprise']);
        $current['platforms'] = self::SUPPORTED_PLATFORMS;

        // Persist into database ai_live_chat_settings
        DB::table('ai_live_chat_settings')->updateOrInsert(
            ['setting_key' => self::SETTING_KEY],
            [
                'setting_value' => json_encode($current, JSON_UNESCAPED_UNICODE),
                'updated_at' => Carbon::now(),
            ]
        );

        return $current['projects'][$targetProject];
    }
}
