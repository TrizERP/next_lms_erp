<?php
// Login gate for the standalone bulk-import scripts in public/excel_upload.
//
// Those scripts take the school and user from the request and never checked a login, so anyone could
// upload a spreadsheet into any school. Now a script runs only after the ERP has handed the browser a
// signed link (see App\Support\ExcelUploadLink): `xk=<expiry>.<hmac of school + user + expiry>`, made with
// APP_KEY. A valid link starts a short PHP session that remembers the school and user; from then on the
// request's own school and user values are replaced with the signed ones.

require_once __DIR__ . '/legacy_env.php';

if (!function_exists('excel_upload_key_ok')) {
    function excel_upload_key_ok($key, $sub, $user, $appKey, $now)
    {
        $key = (string) $key;
        if ($appKey === '' || $appKey === null || strpos($key, '.') === false) {
            return false;
        }
        list($exp, $mac) = explode('.', $key, 2);
        if (!ctype_digit($exp) || (int) $exp < $now || (int) $sub <= 0) {
            return false;
        }
        $expected = hash_hmac('sha256', 'excel_upload|' . (int) $sub . '|' . (int) $user . '|' . $exp, (string) $appKey);

        return hash_equals($expected, $mac);
    }

    function excel_upload_deny()
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Please open this page from the ERP menu.';
        exit;
    }

    function excel_upload_guard()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sub = (int) ($_REQUEST['sub_institute_id'] ?? $_REQUEST['sub_institute_iderp'] ?? 0);
        $user = (int) ($_REQUEST['user_id'] ?? 0);
        $key = $_REQUEST['xk'] ?? '';

        if ($key !== '' && excel_upload_key_ok($key, $sub, $user, legacy_env('APP_KEY'), time())) {
            session_regenerate_id(true);
            $_SESSION['xl_auth'] = ['sub' => $sub, 'user' => $user, 'until' => time() + 4 * 3600];
        }

        $auth = $_SESSION['xl_auth'] ?? null;
        if (!is_array($auth) || (int) ($auth['until'] ?? 0) < time() || (int) ($auth['sub'] ?? 0) <= 0) {
            excel_upload_deny();
        }

        // The signed school and user win over anything the request says.
        foreach (['sub_institute_id', 'sub_institute_iderp'] as $f) {
            if (isset($_REQUEST[$f]) || $f === 'sub_institute_id') {
                $_REQUEST[$f] = $_GET[$f] = $_POST[$f] = (string) $auth['sub'];
            }
        }
        $_REQUEST['user_id'] = $_GET['user_id'] = $_POST['user_id'] = (string) $auth['user'];
        $_SESSION['SUB_INSTITUTE_ID'] = (string) $auth['sub'];
        if (isset($_REQUEST['syear'])) {
            $_REQUEST['syear'] = $_GET['syear'] = $_POST['syear'] = preg_replace('/[^0-9\-]/', '', (string) $_REQUEST['syear']);
        }
    }
}

excel_upload_guard();
