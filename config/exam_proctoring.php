<?php

return [
    'tab_switch_limit' => (int) env('EXAM_PROCTORING_TAB_SWITCH_LIMIT', 3),
    'auto_submit_on_tab_switch' => (bool) env('EXAM_PROCTORING_AUTO_SUBMIT_ON_TAB_SWITCH', true),
    'critical_violation_limit' => (int) env('EXAM_PROCTORING_CRITICAL_VIOLATION_LIMIT', 5),
    'critical_event_types' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('EXAM_PROCTORING_CRITICAL_EVENT_TYPES', 'tab_hidden,fullscreen_exit,copy,cut,paste,context_menu,screenshot_attempt,select_all'))
    ))),
    'inactivity_seconds' => (int) env('EXAM_PROCTORING_INACTIVITY_SECONDS', 300),
    'heartbeat_seconds' => (int) env('EXAM_PROCTORING_HEARTBEAT_SECONDS', 15),
];
