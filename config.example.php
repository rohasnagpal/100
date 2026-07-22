<?php
declare(strict_types=1);

return array(
    // Generate a hash with:
    // php -r "echo password_hash('choose-a-strong-password', PASSWORD_DEFAULT), PHP_EOL;"
    'admin_password_hash' => '',

    // Administrators are logged out after this many seconds of inactivity.
    'session_timeout' => 43_200,
);
