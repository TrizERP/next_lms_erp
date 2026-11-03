<?php

namespace Tests\Feature\Security;

use App\Support\ExcelUploadLink;
use Tests\TestCase;

/**
 * public/excel_upload scripts used to take the school and user from the request with no login.
 * They now run only behind a link signed by the ERP (App\Support\ExcelUploadLink).
 */
class ExcelUploadGuardTest extends TestCase
{
    private function runGuard(array $request, string $appKey = 'base64:testkey'): string
    {
        $script = tempnam(sys_get_temp_dir(), 'xl') . '.php';
        file_put_contents($script, '<?php $_REQUEST = ' . var_export($request, true) . '; $_GET = $_POST = $_REQUEST;'
            . ' putenv("APP_KEY=' . $appKey . '"); require ' . var_export(base_path('bootstrap/excel_upload_guard.php'), true) . ';'
            . ' echo "PASSED sub=" . $_REQUEST["sub_institute_id"] . " user=" . $_REQUEST["user_id"];');
        $out = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1');
        @unlink($script);

        return $out;
    }

    private function key(int $sub, int $user, string $appKey = 'base64:testkey', int $exp = null): string
    {
        $exp ??= time() + 600;

        return $exp . '.' . hash_hmac('sha256', 'excel_upload|' . $sub . '|' . $user . '|' . $exp, $appKey);
    }

    public function test_no_key_is_refused(): void
    {
        $this->assertStringContainsString('Please open this page from the ERP menu', $this->runGuard(['sub_institute_id' => '7', 'user_id' => '3']));
        $this->assertStringNotContainsString('PASSED', $this->runGuard(['sub_institute_id' => '7']));
    }

    public function test_a_valid_key_passes_and_pins_the_school(): void
    {
        $out = $this->runGuard(['sub_institute_id' => '7', 'user_id' => '3', 'xk' => $this->key(7, 3)]);
        $this->assertStringContainsString('PASSED sub=7 user=3', $out);
    }

    public function test_a_key_cannot_be_reused_for_another_school_or_user(): void
    {
        $k = $this->key(7, 3);
        $this->assertStringNotContainsString('PASSED', $this->runGuard(['sub_institute_id' => '8', 'user_id' => '3', 'xk' => $k]));
        $this->assertStringNotContainsString('PASSED', $this->runGuard(['sub_institute_id' => '7', 'user_id' => '4', 'xk' => $k]));
    }

    public function test_expired_or_wrongly_signed_keys_are_refused(): void
    {
        $this->assertStringNotContainsString('PASSED', $this->runGuard(['sub_institute_id' => '7', 'user_id' => '3', 'xk' => $this->key(7, 3, 'base64:testkey', time() - 5)]));
        $this->assertStringNotContainsString('PASSED', $this->runGuard(['sub_institute_id' => '7', 'user_id' => '3', 'xk' => $this->key(7, 3, 'another-key')]));
        $this->assertStringNotContainsString('PASSED', $this->runGuard(['sub_institute_id' => '7', 'user_id' => '3', 'xk' => 'garbage']));
    }

    public function test_the_laravel_side_signs_what_the_guard_accepts(): void
    {
        config(['app.key' => 'base64:testkey']);
        $out = $this->runGuard(['sub_institute_id' => '9', 'user_id' => '2', 'xk' => ExcelUploadLink::key(9, 2)]);
        $this->assertStringContainsString('PASSED sub=9 user=2', $out);
    }

    public function test_every_import_script_loads_the_guard_first(): void
    {
        foreach (['bulk_chapter_data', 'bulk_topic_data', 'bulk_content_data', 'bulk_lo_data', 'bulk_question_data',
                     'Import_xlsx_data', 'fees', 'map_student_document_data', 'export_xlsx', 'ajax'] as $f) {
            $head = implode("\n", array_slice(file(base_path("public/excel_upload/$f.php"), FILE_IGNORE_NEW_LINES), 0, 3));
            $this->assertStringContainsString('excel_upload_guard.php', $head, $f);
        }
    }

    public function test_ajax_lookups_cast_ids_to_integers(): void
    {
        $src = file_get_contents(base_path('public/excel_upload/ajax.php'));
        $this->assertDoesNotMatchRegularExpression('/\? \$_REQUEST\[.(grade|standard|subject|chapter|sub_institute_id).\] :/', $src);
        $this->assertStringContainsString("? (int) \$_REQUEST['standard'] : 0", $src);
    }
}
