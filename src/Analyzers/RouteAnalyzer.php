<?php

namespace YourName\LegacyCleaner\Analyzers;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Collection;
use YourName\LegacyCleaner\Services\CodeSearchService;
use YourName\LegacyCleaner\Support\UsageResult;

class RouteAnalyzer
{
    protected CodeSearchService $searchService;

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

            if ($this->isExcluded($routeName)) {
                continue;
            }

            $usageCount = $this->findUsages($routeName, $routeUri);

            if ($usageCount === 0) {
                $unused->push([
                    'name' => $routeName,
                    'uri' => $routeUri,
                    'method' => implode('|', $route->methods()),
                    'action' => $route->getActionName(),
                ]);
            } else {
                $used->push([
                    'name' => $routeName,
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
            $count += $this->searchService->searchInDirectory(
                resource_path('js'),
                "route\(['\"]$routeName['\"]"
            );

            $count += $this->searchService->searchInDirectory(
                resource_path('views'),
                "route\(['\"]$routeName['\"]"
            );
        }

        // Search for direct URI usage
        $count += $this->searchService->searchInDirectory(
            resource_path(),
            $routeUri
        );

        return $count;
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
