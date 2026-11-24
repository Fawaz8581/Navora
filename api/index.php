<?php

// Ensure writable directories in /tmp for Vercel's read-only serverless environment
$tmpDirs = [
    '/tmp/views',
    '/tmp/cache',
    '/tmp/sessions',
    '/tmp/logs',
];

foreach ($tmpDirs as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Forward Vercel Serverless request to Laravel's public/index.php entrypoint
require __DIR__ . '/../public/index.php';

