<?php

return [
    'enabled' => env('VIRUS_SCANNER_ENABLED', env('APP_ENV') === 'production'),
    'binary' => env('CLAMAV_BINARY', 'clamscan'),
    'timeout' => (int) env('VIRUS_SCANNER_TIMEOUT', 30),
];
