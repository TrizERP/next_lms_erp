<?php

namespace Tests\Feature\AI;

use App\Services\AI\ConversationalSettingsService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConversationalSettingsTest extends TestCase
{
    public function test_get_settings_returns_defaults_or_persisted_state_without_extra_platforms(): void
    {
        $service = new ConversationalSettingsService();
        $all = $service->getCentralizedSettings();

        // 1. Verify original platforms list ONLY (no brain, no enterprise)
        $this->assertEquals(['lms_k12', 'enterprise_brain', 'g2g'], $all['platforms']);
        $this->assertArrayNotHasKey('brain', $all['projects']);
        $this->assertArrayNotHasKey('enterprise', $all['projects']);

        // 2. Module specific query for lms_k12
        $lms = $service->getCentralizedSettings('lms_k12');
        $this->assertArrayHasKey('voice_enabled', $lms);
        $this->assertArrayHasKey('default_language', $lms);
    }

    public function test_save_settings_is_module_specific_and_does_not_affect_other_modules(): void
    {
        $service = new ConversationalSettingsService();

        // 1. Save settings for LMS K-12: Voice disabled, Hindi selected
        $savedLms = $service->saveCentralizedSettings([
            'project_id' => 'lms_k12',
            'voice_enabled' => false,
            'default_language' => 'hi-IN',
        ], 'Kalpesh Sheth (7013)');

        $this->assertFalse($savedLms['voice_enabled']);
        $this->assertEquals('hi-IN', $savedLms['default_language']);

        // 2. Save settings for G2G: Voice enabled, English selected
        $savedG2g = $service->saveCentralizedSettings([
            'project_id' => 'g2g',
            'voice_enabled' => true,
            'default_language' => 'en-IN',
        ], 'Kalpesh Sheth (7013)');

        $this->assertTrue($savedG2g['voice_enabled']);
        $this->assertEquals('en-IN', $savedG2g['default_language']);

        // 3. Verify LMS K-12 is STILL Voice disabled and Hindi (NOT affected by G2G!)
        $lmsCurrent = $service->getCentralizedSettings('lms_k12');
        $this->assertFalse($lmsCurrent['voice_enabled'], 'LMS K-12 voice must remain disabled');
        $this->assertEquals('hi-IN', $lmsCurrent['default_language'], 'LMS K-12 language must remain Hindi');

        // 4. Verify G2G is STILL Voice enabled and English (NOT affected by LMS K-12!)
        $g2gCurrent = $service->getCentralizedSettings('g2g');
        $this->assertTrue($g2gCurrent['voice_enabled'], 'G2G voice must remain enabled');
        $this->assertEquals('en-IN', $g2gCurrent['default_language'], 'G2G language must remain English');

        // 5. Verify database persistence in ai_live_chat_settings
        $row = DB::table('ai_live_chat_settings')
            ->where('setting_key', ConversationalSettingsService::SETTING_KEY)
            ->first();

        $this->assertNotNull($row);
        $decoded = json_decode($row->setting_value, true);
        $this->assertFalse($decoded['projects']['lms_k12']['voice_enabled']);
        $this->assertEquals('hi-IN', $decoded['projects']['lms_k12']['default_language']);
        $this->assertTrue($decoded['projects']['g2g']['voice_enabled']);
        $this->assertEquals('en-IN', $decoded['projects']['g2g']['default_language']);
        $this->assertArrayNotHasKey('brain', $decoded['projects']);
        $this->assertArrayNotHasKey('enterprise', $decoded['projects']);
    }

    public function test_api_endpoints_for_conversational_settings(): void
    {
        $response = $this->getJson('/api/ai/conversation/settings?project_id=lms_k12');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'voice_enabled',
                'default_language',
            ],
        ]);
    }
}
