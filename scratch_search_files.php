<?php
$dirs = [
    'c:/vivek work/next_lms_erp/app',
    'c:/vivek work/next_lms_erp/storage/app',
    'c:/vivek work/lms_k12/app',
    'c:/vivek work/lms_k12/packages',
    'c:/vivek work/lms_k12/scripts',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isDir()) continue;
        $name = strtolower($file->getFilename());
        if (str_contains($name, 'teacher') || str_contains($name, 'rubric') || str_contains($name, 'ksa') || str_contains($name, 'mindmap') || str_contains($name, 'outcome')) {
            echo $file->getPathname() . "\n";
        }
    }
}

