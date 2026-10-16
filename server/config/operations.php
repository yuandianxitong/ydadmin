<?php

return [
    'delivery_lease_seconds' => max(60, (int) env('DELIVERY_LEASE_SECONDS', 3600)),
    'inline_delivery' => true,
    'task_storage' => env('TASK_STORAGE_PATH', runtime_path('private-tasks')),
    'task_max_rows' => max(1, (int) env('TASK_MAX_ROWS', 100000)),
    'task_max_file_bytes' => max(1, (int) env('TASK_MAX_FILE_MB', 50)) * 1024 * 1024,
    'slow_request_ms' => max(1, (int) env('SLOW_REQUEST_MS', 1000)),
    'slow_query_ms' => max(1, (int) env('SLOW_QUERY_MS', 200)),
    'heartbeat_ttl' => 120,
    'require_workers_ready' => (bool) env('REQUIRE_WORKERS_READY', true),
    'task_retention_seconds' => 7 * 86400,
];
