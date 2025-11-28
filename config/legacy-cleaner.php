<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Directories to Analyze
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'controllers' => app_path('Http/Controllers'),
        'models' => app_path('Models'),
        'views' => resource_path('views'),
        'routes' => base_path('routes'),
        'middleware' => app_path('Http/Middleware'),
        'requests' => app_path('Http/Requests'),
        'jobs' => app_path('Jobs'),
        'javascript' => resource_path('js'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclusions
    |--------------------------------------------------------------------------
    */
    'exclude' => [
        'controllers' => [
            'Controller.php',
        ],
        'routes' => [
            // Route names to exclude from analysis
        ],
        'javascript' => [
            '*.d.ts', // TypeScript declaration files
            '*.test.ts', // Test files
            '*.test.js',
            '*.spec.ts',
            '*.spec.js',
            'vite.config.ts',
            'vite.config.js',
            'vitest.config.js',
            'tailwind.config.js',
            'postcss.config.js',
            '*.config.ts',
            '*.config.js',
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Archive Settings
    |--------------------------------------------------------------------------
    */
    'archive' => [
        'enabled' => true,
        'path' => app_path('Archive'),
        'backup_before_archive' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Report Settings
    |--------------------------------------------------------------------------
    */
    'report' => [
        'output_path' => storage_path('app/legacy-cleaner'),
        'format' => 'html', // html, json, markdown
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Patterns
    |--------------------------------------------------------------------------
    */
    'search_patterns' => [
        'route_usage' => [
            "route\(['\"]%s['\"]",
            "Route::get\(['\"]%s['\"]",
            "Route::post\(['\"]%s['\"]",
            "redirect\(\)->route\(['\"]%s['\"]",
        ],
        'controller_usage' => [
            "use %s;",
            "%s::class",
            "new %s",
        ],
    ],
];
