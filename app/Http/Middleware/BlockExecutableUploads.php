<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Refuses any uploaded file that could run on the server.
 *
 * About 290 upload sites across the controllers store files with the client's own name and
 * extension, many under public/. Rather than patch each one, this runs on every request and
 * rejects a file whose name carries a server-side executable extension anywhere in it
 * (`shell.php`, `shell.php.jpg`, `shell.phtml`) or whose content starts with a PHP tag.
 *
 * HTML and SVG are deliberately not blocked here: LMS and H5P content legitimately uses
 * them. Serving those safely is a separate concern from running code on the server.
 */
class BlockExecutableUploads
{
    private const EXECUTABLE = [
        'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar',
        'inc', 'shtml', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'bat', 'cmd', 'ps1', 'exe', 'dll', 'jsp', 'jspx',
        'asp', 'aspx', 'asa', 'cer', 'htaccess', 'htpasswd', 'user.ini',
    ];

    public function handle(Request $request, Closure $next)
    {
        foreach ($this->flatten($request->allFiles()) as $file) {
            if ($file instanceof UploadedFile && $this->isDangerous($file)) {
                return response()->json([
                    'status' => '0',
                    'status_code' => 0,
                    'message' => 'This file type is not allowed.',
                ], 422);
            }
        }

        return $next($request);
    }

    private function isDangerous(UploadedFile $file): bool
    {
        $name = strtolower((string) $file->getClientOriginalName());
        // Every dot-separated part counts, so "a.php.jpg" and "a.jpg.php" are both caught.
        $parts = array_slice(explode('.', ltrim($name, '.')), 1);
        if ($name !== '' && $name[0] === '.') {
            $parts[] = ltrim($name, '.');
        }
        foreach ($parts as $part) {
            if (in_array(trim($part), self::EXECUTABLE, true)) {
                return true;
            }
        }

        $path = $file->getRealPath();
        if ($path && is_readable($path)) {
            $head = (string) @file_get_contents($path, false, null, 0, 512);
            if (preg_match('/<\?php|<\?=|<\?\s/i', $head)) {
                return true;
            }
        }

        return false;
    }

    private function flatten(array $files): array
    {
        $out = [];
        array_walk_recursive($files, function ($f) use (&$out) {
            $out[] = $f;
        });

        return $out;
    }
}
