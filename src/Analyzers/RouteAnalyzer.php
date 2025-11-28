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

            // Skip framework routes
            if ($this->isFrameworkRoute($routeName)) {
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
                    'action' => $route->getActionName(),
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

        // Search for route name usage
        if ($routeName) {
            // Search for route('name') - use simpler string patterns
            $pattern = 'route\([\'"]' . preg_quote($routeName, '~') . '[\'"]';

            $jsPath = resource_path('js');
            $viewsPath = resource_path('views');

            if (is_dir($jsPath)) {
                $count += $this->searchService->searchInDirectory($jsPath, $pattern);
            }

            if (is_dir($viewsPath)) {
                $count += $this->searchService->searchInDirectory($viewsPath, $pattern);
            }
        }

        // Search for direct URI usage (simple string search, not regex)
        // Only search if URI doesn't contain parameters
        if (!str_contains($routeUri, '{')) {
            $jsPath = resource_path('js');
            $viewsPath = resource_path('views');

            if (is_dir($jsPath)) {
                $count += $this->searchInDirectoryForString($jsPath, $routeUri);
            }

            if (is_dir($viewsPath)) {
                $count += $this->searchInDirectoryForString($viewsPath, $routeUri);
            }
        }

        return $count;
    }

    protected function searchInDirectoryForString(string $directory, string $searchString): int
    {
        $count = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'js', 'vue', 'ts', 'tsx', 'jsx'])) {
                    $content = @file_get_contents($file->getPathname());
                    if ($content !== false) {
                        $count += substr_count($content, $searchString);
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently handle directory traversal errors
        }

        return $count;
    }

    protected function isFrameworkRoute(?string $routeName): bool
    {
        if (!$routeName) {
            return false;
        }

        foreach ($this->frameworkPatterns as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    protected function isExcluded(?string $routeName): bool
    {
        if (!$routeName) {
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
