<?php

return [
    'already_installed'   => 'The system is already installed and cannot be installed again.',
    'not_installed'       => 'The system is not installed yet.',
    'database_not_empty'  => 'The target database already has tables. Installation is refused to avoid overwriting existing data.',
    'sql_failed'          => 'Installing SQL failed. The database may be incomplete; drop it manually and retry.',
    'baseline_required'   => 'This database has no upgrade records. Pass --baseline with the current version before upgrading.',
    'invalid_update_dir'  => 'The update directory name or path is invalid and was not loaded.',
    'restart_hint'        => 'Installation finished. Run php start.php restart so the new configuration takes effect.',
    'env_php'             => 'PHP version must be 8.4 or newer.',
    'env_extension'       => 'The :name extension must be installed.',
    'env_writable'        => ':path must be writable.',
];
