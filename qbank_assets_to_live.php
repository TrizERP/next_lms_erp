<?php
/**
 * Move question-bank figures off localhost and onto the live CDN.
 *
 * MinerU writes figures to the extraction machine's disk and the extraction
 * service serves them at http://127.0.0.1:8000/api/assets/... That URL is
 * stored verbatim in lms_question_asset.stored_url, so every figure in the
 * question bank is a broken image for anyone who is not sitting at that
 * machine -- which is everyone on lms-k12.vercel.app.
 *
 * The LMS already has a live asset store: DigitalOcean Spaces, written through
 * Storage::disk('digitalocean') and served from the CDN host below. This
 * uploads each referenced figure there and rewrites the row to the CDN URL, so
 * the bank shows the same image regardless of where it is opened from.
 *
 *   php qbank_assets_to_live.php --dry    # report, upload nothing
 *   php qbank_assets_to_live.php          # upload and rewrite
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Where the extraction service keeps its output on this machine. */
const EXTRACTION_OUTPUT = 'C:/Users/MILAN/Downloads/pdf extraction/backend/output';

/** Spaces prefix and the CDN host the app already serves content from. */
const SPACES_FOLDER = 'lms_content_file/qbank';
const CDN_BASE = 'https://s3-triz.fra1.cdn.digitaloceanspaces.com/public';

$dry = in_array('--dry', $argv, true);

$rows = DB::table('lms_question_asset')
    ->where('stored_url', 'like', 'http://127.0.0.1%')
    ->orWhere('stored_url', 'like', 'http://localhost%')
    ->get(['id', 'question_id', 'stored_url', 'asset_sha256', 'extraction_id']);

printf("%d asset row(s) still point at a local URL.\n\n", $rows->count());

$uploaded = $skipped = $missing = 0;
$missingSamples = [];

foreach ($rows as $row) {
    // /api/assets/<job_id>/<rest...>  ->  <output>/<job_id>/<rest...>
    $path = parse_url($row->stored_url, PHP_URL_PATH) ?? '';
    if (!str_starts_with($path, '/api/assets/')) {
        // The generated-SVG rows use /uploads/qbank/... and are produced by a
        // different tool; their files are not in the extraction output.
        $skipped++;
        continue;
    }

    $relative = substr($path, strlen('/api/assets/'));
    $local = EXTRACTION_OUTPUT . '/' . rawurldecode($relative);

    if (!is_file($local)) {
        $missing++;
        if (count($missingSamples) < 3) {
            $missingSamples[] = $local;
        }
        continue;
    }

    $extension = pathinfo($local, PATHINFO_EXTENSION) ?: 'jpg';
    // Name by content hash: the same figure referenced from two questions is
    // stored once, and a re-run overwrites rather than duplicating.
    $name = ($row->asset_sha256 ?: sha1_file($local)) . '.' . $extension;
    $target = 'public/' . SPACES_FOLDER . '/' . $name;
    $cdnUrl = CDN_BASE . '/' . SPACES_FOLDER . '/' . $name;

    if ($dry) {
        printf("  would upload %s\n            -> %s\n", basename($local), $cdnUrl);
        $uploaded++;
        continue;
    }

    try {
        Storage::disk('digitalocean')->put($target, file_get_contents($local), 'public');
        DB::table('lms_question_asset')->where('id', $row->id)->update(['stored_url' => $cdnUrl]);
        $uploaded++;
        printf("  uploaded %s\n", $cdnUrl);
    } catch (Throwable $e) {
        printf("  FAILED %s: %s\n", basename($local), $e->getMessage());
    }
}

printf(
    "\n%s: %d figure(s), %d file(s) missing on disk, %d row(s) skipped (not extraction assets).\n",
    $dry ? 'Dry run' : 'Done',
    $uploaded,
    $missing,
    $skipped
);

foreach ($missingSamples as $sample) {
    printf("  missing: %s\n", $sample);
}
