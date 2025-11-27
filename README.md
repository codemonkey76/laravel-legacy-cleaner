# Laravel Legacy Cleaner

Detect and manage unused/legacy code in your Laravel applications.

## Installation
```bash
composer require yourname/laravel-legacy-cleaner --dev
```

## Configuration

Publish the configuration file:
```bash
php artisan vendor:publish --tag=legacy-cleaner-config
```

## Usage

### Analyze Routes
```bash
php artisan legacy:analyze-routes
php artisan legacy:analyze-routes --output=routes-report.json
```

### Analyze Controllers
```bash
php artisan legacy:analyze-controllers
```

### Generate Full Report
```bash
php artisan legacy:report
php artisan legacy:report --format=json
php artisan legacy:report --format=markdown --output=report.md
```

### Archive Unused Code
```bash
# Dry run (preview what would be archived)
php artisan legacy:archive --dry-run

# Actually archive
php artisan legacy:archive
```

## Features

- ✅ Detect unused routes
- ✅ Detect unused controllers and methods
- ✅ Detect unused models
- ✅ Detect unused views
- ✅ Detect unused middleware
- ✅ Detect unused form requests
- ✅ Generate comprehensive reports (HTML, JSON, Markdown)
- ✅ Archive unused code safely
- ✅ Configurable exclusions
- ✅ Dry-run mode

## Configuration Options
```php
return [
    'paths' => [
        'controllers' => app_path('Http/Controllers'),
        'models' => app_path('Models'),
        // ...
    ],
    
    'exclude' => [
        'controllers' => ['Controller.php'],
        'routes' => ['debugbar.*'],
    ],
    
    'archive' => [
        'enabled' => true,
        'path' => app_path('Archive'),
    ],
];
```

## License

MIT
