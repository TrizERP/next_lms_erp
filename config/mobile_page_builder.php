<?php

return [

    /*
    | The lms_k12 Next.js origin that serves a Custom Mobile Page's runtime
    | route (/mobile/custom/{slug}). Used only to COMPUTE the web_url stored
    | on a menu row when an admin picks a Custom Mobile Page in Mobile App
    | Menu Rights -- see MobileAppMenuRightsApiController::updateConfig().
    |
    | One global value for the whole installation, the same way
    | config/cors.php's allowed_origins is one global list rather than
    | per-tenant. Must be an origin CORS already trusts (config/cors.php),
    | or MobileWebHandoffApiController will refuse to hand off to it.
    |
    | Both https://k12.scholarclone.com and https://lms-k12.vercel.app are in
    | active use; override per environment with MOBILE_PAGE_BUILDER_FRONTEND_URL
    | if the default below should be the other one.
    */
    'frontend_url' => rtrim((string) env('MOBILE_PAGE_BUILDER_FRONTEND_URL', 'https://k12.scholarclone.com'), '/'),

    /*
    | Disk a Custom Mobile Page's uploaded background/asset images are stored
    | on, via MobilePageAssetUploadService. Matches ContentUploadService's own
    | default -- 'digitalocean' is what the vast majority of this app's
    | uploads already use.
    */
    'upload_disk' => env('MOBILE_PAGE_BUILDER_UPLOAD_DISK', 'digitalocean'),

];
