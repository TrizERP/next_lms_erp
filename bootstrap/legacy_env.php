<?php
// Minimal .env reader for the standalone scripts in public/ that do not boot Laravel.
// Secrets must live in the untracked .env, never in these scripts.

if (!function_exists('legacy_env')) {
    function legacy_env($key, $default = null)
    {
        static $vars = null;

        if ($vars === null) {
            $vars = [];
            $file = __DIR__ . '/../.env';
            if (is_readable($file)) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                        continue;
                    }
                    list($k, $v) = explode('=', $line, 2);
                    $v = trim($v);
                    if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
                        $v = substr($v, 1, -1);
                    }
                    $vars[trim($k)] = $v;
                }
            }
        }

        $env = getenv($key);
        if ($env !== false) {
            return $env;
        }

        return array_key_exists($key, $vars) ? $vars[$key] : $default;
    }
}
