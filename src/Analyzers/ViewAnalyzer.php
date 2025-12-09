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
        // Inertia root view (or any hard-wired root) will never appear in normal scans
        if ($viewName === 'app') {
            return 1;
        }

        // Common vendor overrides are resolved internally by the framework and
        // are effectively always "used" if present.
        if (
            Str::startsWith($viewName, 'vendor.mail.') ||
            Str::startsWith($viewName, 'vendor.livewire.') ||
            Str::startsWith($viewName, 'vendor.pagination.')
        ) {
            return 1;
        }

        $quoted = preg_quote($viewName, '/');
        $count = 0;

        // Search for view('view.name')
        $count += $this->searchService->searchInDirectory(
            app_path(),
            "view\(['\"]{$quoted}['\"]"
        );

        // Search for View::make('view.name')
        $count += $this->searchService->searchInDirectory(
            app_path(),
            "View::make\(['\"]{$quoted}['\"]"
        );

        // Search for PDF::loadView('view.name')
        $count += $this->searchService->searchInDirectory(
            app_path(),
            "loadView\(['\"]{$quoted}['\"]"
        );

        // Classic Mailables: ->markdown('view.name')
        $count += $this->searchService->searchInDirectory(
            app_path(),
            "->markdown\(['\"]{$quoted}['\"]"
        );

        // New Mailables (Laravel 9+): Content(markdown: 'view.name', ...)
        $count += $this->searchService->searchInDirectory(
            app_path(),
            "markdown\s*:\s*['\"]{$quoted}['\"]"
        );

        // Blade directives: @extends, @include*, @component('view.name')
        $count += $this->searchService->searchInDirectory(
            resource_path('views'),
            "@(?:extends|include|includeIf|includeWhen|includeUnless|includeFirst|component)\(['\"]{$quoted}['\"]"
        );

        // Search for <x-*> usage if it's a component under resources/views/components
        if (Str::startsWith($viewName, 'components.')) {
            $componentName = str_replace('components.', '', $viewName);
            $componentTag = str_replace('.', '-', $componentName);

            $count += $this->searchService->searchInDirectory(
                resource_path('views'),
                "<x-{$componentTag}"
            );
        }

        // Inertia::render('Pages/SomePage') – this covers your Vue page components
        $count += $this->searchService->searchInDirectory(
            app_path(),
            "Inertia::render\(['\"]{$quoted}['\"]"
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
