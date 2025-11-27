# Laravel Legacy Cleaner

[![Latest Version on Packagist](https://img.shields.io/packagist/v/codemonkey76/laravel-legacy-cleaner.svg?style=flat-square)](https://packagist.org/packages/codemonkey76/laravel-legacy-cleaner)
[![Total Downloads](https://img.shields.io/packagist/dt/codemonkey76/laravel-legacy-cleaner.svg?style=flat-square)](https://packagist.org/packages/codemonkey76/laravel-legacy-cleaner)

A powerful Laravel package to detect and manage unused/legacy code in your Laravel applications. Helps you identify unused controllers, models, routes, and views to keep your codebase clean and maintainable.

## Features

- 🔍 **Analyze Controllers** - Find unused controllers and methods
- 📦 **Analyze Models** - Detect unused Eloquent models
- 🛣️ **Analyze Routes** - Identify unused routes
- 👁️ **Analyze Views** - Find unused Blade templates
- 📊 **Generate Reports** - Create comprehensive HTML, JSON, or Markdown reports
- 🗄️ **Archive Unused Code** - Safely move unused code to an archive directory
- ⚙️ **Configurable** - Customize paths, exclusions, and search patterns

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x

## Installation

Install the package via Composer:

```bash
composer require codemonkey76/laravel-legacy-cleaner --dev
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=legacy-cleaner-config
```

This will create a `config/legacy-cleaner.php` file where you can customize the package settings.

## Usage

### Analyze Controllers

Find unused controllers and methods in your application:

```bash
# Basic analysis
php artisan legacy:analyze-controllers

# Show unused methods in used controllers
php artisan legacy:analyze-controllers --show-methods

# Export results to JSON
php artisan legacy:analyze-controllers --format=json --output=storage/controllers.json

# Export to CSV
php artisan legacy:analyze-controllers --format=csv --output=storage/controllers.csv
```

**Output:**

```
Analyzing controllers...

📊 Summary:
+---------------------+-------+
| Metric              | Count |
+---------------------+-------+
| Total Controllers   | 25    |
| Used Controllers    | 20    |
| Unused Controllers  | 5     |
| Unused Percentage   | 20%   |
+---------------------+-------+

⚠️  Found 5 potentially unused controllers:
```

### Analyze Models

Detect unused Eloquent models:

```bash
# Basic analysis
php artisan legacy:analyze-models

# Also check if database tables exist
php artisan legacy:analyze-models --check-tables

# Export results
php artisan legacy:analyze-models --format=json --output=storage/models.json
```

**Features:**

- Finds models with no code references
- Counts relationships defined in models
- Optionally verifies database table existence
- Reports used models with missing tables

### Analyze Routes

Identify unused routes:

```bash
# Analyze all routes
php artisan legacy:analyze-routes

# Export results
php artisan legacy:analyze-routes --format=json --output=storage/routes.json
```

The analyzer searches for route usage in:

- Blade views (`route('name')`)
- JavaScript files
- Direct URI references
- Controller redirects

### Analyze Views

Find unused Blade templates:

```bash
# Analyze views (excluding partials)
php artisan legacy:analyze-views

# Include partial views in analysis
php artisan legacy:analyze-views --include-partials

# Export results
php artisan legacy:analyze-views --format=json --output=storage/views.json
```

**View Types Detected:**

- Layouts (`resources/views/layouts/*`)
- Components (`resources/views/components/*`)
- Partials (files starting with `_`)
- Regular views

The analyzer searches for:

- `view()` helper calls
- `View::make()` calls
- `@extends`, `@include`, `@component` directives
- `<x-component />` usage
- `Inertia::render()` calls

### Generate Comprehensive Report

Create a full analysis report in multiple formats:

```bash
# Generate HTML report (default)
php artisan legacy:report

# Generate JSON report
php artisan legacy:report --format=json

# Generate Markdown report
php artisan legacy:report --format=markdown

# Specify output location
php artisan legacy:report --output=storage/app/reports/legacy-analysis.html
```

**HTML Report Features:**

- Beautiful, responsive design
- Summary statistics for each category
- Color-coded results
- Sortable tables
- Timestamp of generation

### Archive Unused Code

Safely move unused code to an archive directory:

```bash
# Preview what would be archived (dry run)
php artisan legacy:archive --dry-run

# Archive unused controllers
php artisan legacy:archive

# Archive specific types
php artisan legacy:archive --type=controllers --type=views
```

**Safety Features:**

- Confirmation prompt before archiving
- Dry-run mode to preview changes
- Preserves directory structure in archive
- Optional backup before archiving (configurable)

## Configuration

After publishing the config file (`config/legacy-cleaner.php`), you can customize:

### Paths to Analyze

```php
'paths' => [
    'controllers' => app_path('Http/Controllers'),
    'models' => app_path('Models'),
    'views' => resource_path('views'),
    'routes' => base_path('routes'),
    'middleware' => app_path('Http/Middleware'),
    'requests' => app_path('Http/Requests'),
    'jobs' => app_path('Jobs'),
],
```

### Exclusions

Exclude specific files or patterns from analysis:

```php
'exclude' => [
    'controllers' => [
        'Controller.php',  // Base controller
    ],
    'routes' => [
        'api.*',           // Exclude all API routes
        'admin.*',         // Exclude admin routes
    ],
],
```

### Archive Settings

```php
'archive' => [
    'enabled' => true,
    'path' => app_path('Archive'),
    'backup_before_archive' => true,
],
```

### Report Settings

```php
'report' => [
    'output_path' => storage_path('app/legacy-cleaner'),
    'format' => 'html', // html, json, markdown
],
```

### Custom Search Patterns

Define custom patterns for finding code usage:

```php
'search_patterns' => [
    'route_usage' => [
        "route\(['\"]%s['\"]",
        "Route::get\(['\"]%s['\"]",
        "redirect\(\)->route\(['\"]%s['\"]",
    ],
    'controller_usage' => [
        "use %s;",
        "%s::class",
        "new %s",
    ],
],
```

## Command Reference

| Command | Description | Options |
|---------|-------------|---------|
| `legacy:analyze-controllers` | Analyze controllers | `--show-methods`, `--format`, `--output` |
| `legacy:analyze-models` | Analyze models | `--check-tables`, `--format`, `--output` |
| `legacy:analyze-routes` | Analyze routes | `--format`, `--output` |
| `legacy:analyze-views` | Analyze views | `--include-partials`, `--format`, `--output` |
| `legacy:report` | Generate comprehensive report | `--format`, `--output` |
| `legacy:archive` | Archive unused code | `--dry-run`, `--type` |

## How It Works

### Controller Analysis

1. Scans all PHP files in the controllers directory
2. Checks if controllers are referenced in routes
3. Searches for controller usage in codebase (imports, instantiation)
4. Identifies unused public methods in controllers

### Model Analysis

1. Finds all classes extending `Illuminate\Database\Eloquent\Model`
2. Searches for model references in controllers, views, and routes
3. Counts defined relationships
4. Optionally checks if corresponding database tables exist

### Route Analysis

1. Retrieves all registered routes
2. Searches for route name usage in Blade views
3. Searches for route usage in JavaScript files
4. Looks for direct URI references

### View Analysis

1. Scans Blade template files
2. Categorizes views (layouts, components, partials, regular views)
3. Searches for view references in controllers
4. Checks for `@extends`, `@include`, component usage
5. Detects Inertia.js usage

## Best Practices

### Before Archiving

1. **Always run with `--dry-run` first**

   ```bash
   php artisan legacy:archive --dry-run
   ```

2. **Review the analysis carefully** - Some code might appear unused but could be:
   - Called dynamically
   - Used in external packages
   - Required for future features
   - API endpoints called by mobile apps

3. **Generate a report for documentation**

   ```bash
   php artisan legacy:report --output=reports/pre-cleanup-$(date +%Y%m%d).html
   ```

4. **Commit your changes** before archiving

### Regular Maintenance

Run analysis regularly (e.g., monthly) to:

- Keep track of code health
- Identify bloat early
- Monitor technical debt
- Plan refactoring efforts

### CI/CD Integration

Add to your CI pipeline:

```yaml
# .github/workflows/code-analysis.yml
- name: Analyze Legacy Code
  run: |
    php artisan legacy:analyze-controllers --format=json --output=controllers.json
    php artisan legacy:analyze-models --format=json --output=models.json
```

## Limitations

### False Positives

The package may report false positives for:

- **Dynamic code execution** - Code called via `call_user_func`, variable functions
- **External API routes** - Routes only called by mobile apps or external services
- **Event-driven code** - Code triggered by events/listeners
- **Reflection-based usage** - Code loaded via reflection
- **String-based references** - `view("some.{$variable}.path")`

### Not Detected

The analyzer cannot detect:

- Code called from package service providers
- Routes/views defined in external packages
- Usage in JavaScript frameworks (Vue, React) with complex routing
- Database migrations and seeders

## Troubleshooting

### "Class not found" errors

Make sure your autoloader is up to date:

```bash
composer dump-autoload
```

### Large codebases timing out

Increase PHP memory limit and max execution time:

```bash
php -d memory_limit=512M -d max_execution_time=300 artisan legacy:analyze-controllers
```

### Missing results

Check your configuration:

```php
// Verify paths in config/legacy-cleaner.php
'paths' => [
    'controllers' => app_path('Http/Controllers'),
    // ...
],
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Setup

```bash
git clone https://github.com/codemonkey76/laravel-legacy-cleaner.git
cd laravel-legacy-cleaner
composer install
```

### Running Tests

```bash
composer test
```

## Security

If you discover any security-related issues, please email <shane@bjja.com.au> instead of using the issue tracker.

## Credits

- [Shane Poppleton](https://github.com/codemonkey76)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

If you find this package helpful, please consider:

- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting new features
- 📖 Improving documentation

---

**Made with ❤️ for the Laravel community**
