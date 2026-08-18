<?php

return [
    // Fallback "created_by" used only when no user is present in session
    // (e.g. console/queue triggered transfers). Normal web requests use
    // the logged-in user's session('user_id').
    'default_created_by' => env('HPC_TRANSFER_DEFAULT_CREATED_BY', null),
];
