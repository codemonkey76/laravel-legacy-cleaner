<?php

namespace Codemonkey76\LegacyCleaner\Analyzers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Codemonkey76\LegacyCleaner\Services\CodeSearchService;
use Codemonkey76\LegacyCleaner\Support\UsageResult;

class ViewAnalyzer
{
    protected CodeSearchService $searchService;

    public function __construct(CodeSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function analyze(bool $includePartials = false): UsageResult
    {
        $viewPath = config('legacy-cleaner.paths.views');
        $views = $this->getViews($viewPath);

        $unused = collect();
        $used = collect();

        foreach ($views as $view) {
            // Skip partials if not included
            if (!$includePartials && $view['type'] === 'partial') {
                continue;
            }

            $result = $this->analyzeView($view);

            if ($result['is_unused']) {
                $unused->push($result);
            } else {
                $used->push($result);
            }
        }

        return new UsageResult($unused, $used);
    }

    protected function getViews(string $path): array
    {
        $views = [];

        $files = File::allFiles($path);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($path . '/', '', $file->getPathname());
            $viewName = str_replace(['/', '.blade.php'], ['.', ''], $relativePath);

            $views[] = [
                'name' => $viewName,
                'path' => $file->getPathname(),
                'type' => $this->determineViewType($viewName, $file->getPathname()),
                'size' => $file->getSize(),
                'last_modified' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }

        return $views;
    }

    protected function analyzeView(array $view): array
    {
        $usageCount = $this->findViewUsages($view['name']);

        return array_merge($view, [
            'is_unused' => $usageCount === 0,
            'references' => $usageCount,
        ]);
    }

    protected function findViewUsages(string $viewName): int
    {
        $count = 0;

        // Search for view() calls
        $count += $this->searchService->searchInDirectory(
            app_path(),
            "view\(['\"]" . preg_quote($viewName, '/') . "['\"]"
        );

        // Search for View::make() calls
        $count += $this->searchService->searchInDirectory(
            app_path(),
            "View::make\(['\"]" . preg_quote($viewName, '/') . "['\"]"
        );

        // Search for @extends, @include, @component in other views
        $count += $this->searchService->searchInDirectory(
            resource_path('views'),
            "@(?:extends|include|component)\(['\"]" . preg_quote($viewName, '/') . "['\"]"
        );

        // Search for <x-component /> usage if it's a component
        if (Str::startsWith($viewName, 'components.')) {
            $componentName = str_replace('components.', '', $viewName);
            $componentTag = str_replace('.', '-', $componentName);

            $count += $this->searchService->searchInDirectory(
                resource_path('views'),
                "<x-{$componentTag}"
            );
        }

        // Search for Inertia::render() if using Inertia
        $count += $this->searchService->searchInDirectory(
            app_path(),
            "Inertia::render\(['\"]" . preg_quote($viewName, '/') . "['\"]"
        );

        return $count;
    }

    protected function determineViewType(string $viewName, string $path): string
    {
        // Check if it's a layout
        if (Str::contains($path, '/layouts/')) {
            return 'layout';
        }

        // Check if it's a component
        if (Str::contains($path, '/components/')) {
            return 'component';
        }

        // Check if it's a partial (starts with underscore)
        if (Str::startsWith(basename($path), '_')) {
            return 'partial';
        }

        return 'view';
    }
}
