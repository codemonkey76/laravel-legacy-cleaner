<?php

namespace Codemonkey76\LegacyCleaner\Analyzers;

use Illuminate\Routing\RouteCollection;
use Codemonkey76\LegacyCleaner\Services\CodeSearchService;
use Codemonkey76\LegacyCleaner\Support\UsageResult;

class RouteAnalyzer
{
    protected CodeSearchService $searchService;

    // Framework routes that should be excluded by default
    protected array $frameworkPatterns = [
        'horizon.*',
        'telescope*',
        'sanctum.*',
        'livewire.*',
        'ignition.*',
        '_ignition.*',
        'debugbar.*',
        'password.*',
        'verification.*',
    ];

    public function __construct(CodeSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function analyze(RouteCollection $routes): UsageResult
    {
        $unused = collect();
        $used = collect();

        foreach ($routes as $route) {
            $routeName = $route->getName();
            $routeUri = $route->uri();
            $action = $route->getActionName();

            // Skip framework routes by URI or action
            if ($this->isFrameworkRoute($routeName, $routeUri, $action)) {
                continue;
            }

            // Skip if excluded in config
            if ($this->isExcluded($routeName)) {
                continue;
            }

            $usageCount = $this->findUsages($routeName, $routeUri);

            if ($usageCount === 0) {
                $unused->push([
                    'name' => $routeName ?: 'Unnamed',
                    'uri' => $routeUri,
                    'method' => implode('|', $route->methods()),
                    'action' => $action,
                ]);
            } else {
                $used->push([
                    'name' => $routeName ?: 'Unnamed',
                    'uri' => $routeUri,
                    'usage_count' => $usageCount,
                ]);
            }
        }

        return new UsageResult($unused, $used);
    }

    protected function findUsages(?string $routeName, string $routeUri): int
    {
        $count = 0;

        // Directories to scan for route name usage
        $nameSearchPaths = [
            resource_path('js'),
            resource_path('views'),
            app_path(),
            base_path('routes'),
            base_path('tests'),
        ];

        // Search for route name usage
        if ($routeName && $routeName !== 'Unnamed') {
            $quotedName = preg_quote($routeName, '~');

            $patterns = [
                // route('name')
                "route\(['\"]{$quotedName}['\"]",
                // to_route('name')
                "to_route\(['\"]{$quotedName}['\"]",
                // redirect()->route('name')
                "redirect\(\)->route\(['\"]{$quotedName}['\"]",
                // URL::route('name')
                "URL::route\(['\"]{$quotedName}['\"]",
                // routeIs('name')
                "routeIs\(['\"]{$quotedName}['\"]",
            ];

            foreach ($nameSearchPaths as $path) {
                if (!is_dir($path)) {
                    continue;
                }

                foreach ($patterns as $pattern) {
                    $count += $this->searchService->searchInDirectory($path, $pattern);
                }
            }
        }

        // Search for direct URI usage (as a string literal), only if URI has no parameters
        if (!str_contains($routeUri, '{')) {
            $uriSearchPaths = [
                resource_path('js'),
                resource_path('views'),
                app_path(),
                base_path('routes'),
            ];

            // Normalize URI and build a pattern that matches either 'login' or '/login'
            $trimmed = ltrim($routeUri, '/');
            $plain = preg_quote($trimmed, '~');
            $withSlash = '\/' . $plain;
            $uriPattern = "['\"`]({$withSlash}|{$plain})['\"`]";

            foreach ($uriSearchPaths as $path) {
                if (!is_dir($path)) {
                    continue;
                }

                $count += $this->searchService->searchInDirectory($path, $uriPattern);
            }
        }

        return $count;
    }

    protected function isFrameworkRoute(?string $routeName, string $routeUri, string $action): bool
    {
        // Check by route name
        if ($routeName) {
            foreach ($this->frameworkPatterns as $pattern) {
                if (fnmatch($pattern, $routeName)) {
                    return true;
                }
            }
        }

        // Check by URI prefix
        $frameworkUriPrefixes = [
            'horizon/',
            'telescope/',
            '_ignition/',
            'sanctum/',
            'livewire/',
            '_debugbar/',
        ];

        foreach ($frameworkUriPrefixes as $prefix) {
            if (str_starts_with($routeUri, $prefix)) {
                return true;
            }
        }

        // Check by controller namespace
        $frameworkNamespaces = [
            'Laravel\\Horizon\\',
            'Laravel\\Telescope\\',
            'Spatie\\LaravelIgnition\\',
            'Laravel\\Sanctum\\',
            'Livewire\\',
            'Barryvdh\\Debugbar\\',
        ];

        foreach ($frameworkNamespaces as $namespace) {
            if (str_starts_with($action, $namespace)) {
                return true;
            }
        }

        return false;
    }

    protected function isExcluded(?string $routeName): bool
    {
        if (!$routeName || $routeName === 'Unnamed') {
            return false;
        }

        $excluded = config('legacy-cleaner.exclude.routes', []);

        foreach ($excluded as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
