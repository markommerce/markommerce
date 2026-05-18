<?php

declare(strict_types=1);

return [
    'entry' => 'packages/frontend-demo/resources/js/main.ts',
    'useDevServer' => false,
    'devServerUrl' => 'http://localhost:5173',
    'buildDirectory' => 'build',
    'manifestFilename' => '.vite/manifest.json',
    'devServerStylesheets' => [
        'packages/frontend/resources/css/layers.css',
        'packages/theme-blank/resources/css/tokens.css',
        'packages/theme-blank/resources/css/base.css',
        'packages/theme-blank/resources/css/layouts.css',
        'packages/theme-blank/resources/css/components.css',
    ],
];
