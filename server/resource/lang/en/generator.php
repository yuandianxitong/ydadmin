<?php

// Code generator (spec §10). This task only uses table_require / table_not_found;
// the rest are registered here up front for the later preview()/generate() tasks to reuse,
// so the language file is not created twice by concurrent tasks.
return [
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
