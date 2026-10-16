<?php
// Run ONLY after you've confirmed the new Gamma-generated deck looks good.
//   php artisan tinker --execute="require 'C:/xampp/htdocs/next_lms_erp/content_bundles/std-9/mathematics/orienting-yourself-the-use-of-coordinates/assets/hide_old_row.php';"
//
// Soft-hides content_master row #61460 (show_hide = 0) so only the new Gamma
// deck shows in the Teacher Resources tab. Nothing is deleted - this is
// reversible by setting show_hide back to 1.

$row = DB::table('content_master')->where('id', 61460)->first(['id', 'chapter_id', 'content_category']);
if (!$row || (int) $row->chapter_id !== 8594 || $row->content_category !== 'Teacher training presentation') {
    throw new RuntimeException('Row 61460 is not the expected chapter 8594 Teacher training presentation row - stopping.');
}

$updated = DB::table('content_master')->where('id', 61460)->update(['show_hide' => 0]);
echo "Rows hidden: $updated\n";
