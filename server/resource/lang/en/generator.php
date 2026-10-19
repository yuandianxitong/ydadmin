<?php

// Code generator (spec §10). This task only uses table_require / table_not_found;
// the rest are registered here up front for the later preview()/generate() tasks to reuse,
// so the language file is not created twice by concurrent tasks.
return [
    'invalid_select_option' => 'Option values do not match the database type for :field',
    'frontend_mount_missing' => 'Mount the host admin/ source at /var/www/admin before generating code',
    'select_options_required' => 'Configure options for :field before generating a select',
    'generate_incomplete' => 'Generation has conflicts or failed files; inspect the file results',
    'path_changed' => 'Artifact path changed; migrate it manually first',
    'generated_file_deleted' => 'Generated file was deleted; review whether to restore it',
    'baseline_missing' => 'Existing file has no trusted generation baseline and cannot be overwritten automatically',
    'merge_conflict' => 'Custom and generated edits overlap, or exceed the automatic merge budget',
    'invalid_merge' => 'Merged output failed PHP or module descriptor validation',
    'plan_expired' => 'Generation plan expired; preview it again',
    'source_changed' => 'Source changed during the update; preview it again',
    'frontend_preserved' => 'Preserved custom frontend page code',
    'backup' => 'Backup',
    'module_busy' => 'This module is being generated; try again later',
    'table_require'           => 'Please select a table',
    'table_not_found'         => 'Table not found',
    'disabled_in_production'  => 'Code generator is disabled in production',
    'file_exists'             => 'Target file already exists',
    'write_failed'            => 'Failed to write file',
    'render_failed'           => 'Failed to render template',
    'invalid_module_name'     => 'Module name must start with a lowercase letter and contain only lowercase letters, digits and underscores (max 31 chars)',
    'invalid_model_name'      => 'Model name must start with an uppercase letter and contain only letters and digits (max 41 chars)',
    'module_name_reserved'    => 'Module name collides with an existing language group (admin_log/auth/business/messages/validation/generator/apidoc), please choose another',
    'invalid_table_comment'   => 'Table comment must not contain line breaks or angle brackets, and is limited to 100 characters',
    'unknown_error'           => 'Unknown error',
];
