<?php

declare(strict_types=1);

return [
    'invalid_idempotency_key' => 'Idempotency-Key must contain 8 to 128 letters, digits or . _ : -',
    'idempotency_conflict' => 'This idempotency key was used for a different request',
    'response_too_large' => 'Response exceeds the idempotency storage limit',
    'invalid_import' => 'Upload a CSV file within the configured size limit',
    'invalid_export' => 'Unsupported export module',
    'not_ready' => 'Service dependencies are not ready',
    'import_task' => 'Submit user import task',
    'export_task' => 'Submit data export task',
    'cancel_task' => 'Cancel background task',
    'task_not_found' => 'Task does not exist or has expired',
    'task_not_ready' => 'Export file is not ready',
];
