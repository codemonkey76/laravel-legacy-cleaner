<?php

namespace Codemonkey76\LegacyCleaner\Analyzers;

use Illuminate\Support\Facades\File;
use Codemonkey76\LegacyCleaner\Services\CodeSearchService;
use Codemonkey76\LegacyCleaner\Support\UsageResult;

class JavaScriptAnalyzer
{
    protected CodeSearchService $searchService;
    protected ?array $inertiaRenders = null;

    // Files that are typically entry points and should be excluded
    protected array $entryPointPatterns = [
        'app.js',
        'app.ts',
        'main.js',
        'main.ts',
        'bootstrap.js',
        'bootstrap.ts',
    ];

    // Patterns for files that are auto-loaded (like layouts)
    protected array $autoLoadedPatterns = [
        'layouts/*',
        '*/layouts/*',
        'Layouts/*',
        '*/Layouts/*',
    ];

    public function __construct(CodeSearchService $searchService)
    {
        $this->searchService = $searchService;

        // Load auto-loaded patterns from config, merge with defaults
        $configPatterns = config('legacy-cleaner.javascript.auto_loaded_patterns', []);
        $this->autoLoadedPatterns = array_merge($this->autoLoadedPatterns, $configPatterns);
    }

    public function analyze(?string $jsPath = null): UsageResult
    {
        $jsPath = $jsPath ?? config('legacy-cleaner.paths.javascript', resource_path('js'));

        if (!is_dir($jsPath)) {
            return new UsageResult(collect(), collect());
        }

        $files = $this->getJavaScriptFiles($jsPath);

        $unused = collect();
        $used = collect();

        foreach ($files as $fileInfo) {
            $result = $this->analyzeFile($fileInfo, $jsPath);

            if ($result['is_unused']) {
                $unused->push($result);
            } else {
                $used->push($result);
            }
        }

        return new UsageResult($unused, $used);
    }

    protected function getJavaScriptFiles(string $path): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = $file->getExtension();
                if (in_array($extension, ['js', 'ts', 'vue', 'jsx', 'tsx', 'mjs'])) {
                    // Skip files matching exclusion patterns
                    if ($this->isExcludedByPattern($file->getFilename())) {
                        continue;
                    }

                    $files[] = [
                        'path' => $file->getPathname(),
                        'name' => $file->getFilename(),
                        'relative_path' => str_replace($path . '/', '', $file->getPathname()),
                        'extension' => $extension,
                        'size' => $file->getSize(),
                        'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                }
            }
        }

        return $files;
    }

    protected function analyzeFile(array $fileInfo, string $basePath): array
    {
        // Skip entry points
        if ($this->isEntryPoint($fileInfo['name'])) {
            return array_merge($fileInfo, [
                'is_unused' => false,
                'references' => -1, // -1 indicates "skipped as entry point"
                'reason' => 'Entry point file',
            ]);
        }

        // Skip auto-loaded files (like layouts)
        if ($this->isAutoLoaded($fileInfo['relative_path'])) {
            return array_merge($fileInfo, [
                'is_unused' => false,
                'references' => -1,
                'reason' => 'Auto-loaded file (Layouts)',
            ]);
        }

        // Find references via imports
        $importReferences = $this->findFileReferences($fileInfo, $basePath);

        // Check if file is rendered by Inertia
        $inertiaReference = $this->isRenderedByInertia($fileInfo, $basePath);

        $totalReferences = $importReferences + ($inertiaReference ? 1 : 0);

        $reason = null;
        if ($totalReferences === 0) {
            $reason = 'No imports or Inertia::render() found';
        } elseif ($inertiaReference && $importReferences === 0) {
            $reason = 'Rendered by Inertia';
        }

        return array_merge($fileInfo, [
            'is_unused' => $totalReferences === 0,
            'references' => $totalReferences,
            'reason' => $reason,
        ]);
    }

    protected function findFileReferences(array $fileInfo, string $basePath): int
    {
        $count = 0;

        // Get the file name without extension for import matching
        $fileNameWithoutExt = pathinfo($fileInfo['name'], PATHINFO_FILENAME);

        // Search patterns for different import styles
        $patterns = [
            // ES6 imports
            "from ['\"].*/" . preg_quote($fileNameWithoutExt, '~') . "['\"]",
            "from ['\"].*/" . preg_quote($fileNameWithoutExt, '~') . "\\.",

            // Relative imports
            "from ['\"]\\..*/" . preg_quote($fileNameWithoutExt, '~'),

            // Dynamic imports
            "import\(['\"].*/" . preg_quote($fileNameWithoutExt, '~'),

            // Require statements
            "require\(['\"].*/" . preg_quote($fileNameWithoutExt, '~'),
        ];

        // Search in all JS/TS/Vue files
        foreach ($patterns as $pattern) {
            $count += $this->searchService->searchInDirectory($basePath, $pattern);
        }

        // Also check in Blade files for script imports
        $viewsPath = resource_path('views');
        if (is_dir($viewsPath)) {
            // Look for <script src="..."> tags
            $scriptPattern = '<script.*src=["\'].*' . preg_quote($fileNameWithoutExt, '~');
            $count += $this->searchService->searchInDirectory($viewsPath, $scriptPattern);
        }

        return $count;
    }

    protected function isRenderedByInertia(array $fileInfo, string $_basePath): bool
    {
        // Only check Vue files in Pages directory for Inertia rendering
        if ($fileInfo['extension'] !== 'vue') {
            return false;
        }

        // Check if file is in a Pages directory
        if (
            !preg_match('#/Pages/#', $fileInfo['relative_path']) &&
            !preg_match('#^Pages/#', $fileInfo['relative_path'])
        ) {
            return false;
        }

        // Lazy load Inertia renders on first use
        if ($this->inertiaRenders === null) {
            $this->inertiaRenders = $this->getInertiaRenders();
        }

        // Convert file path to Inertia component name
        // e.g., "Pages/Admin/CustomerIndex.vue" -> "Admin/CustomerIndex"
        $componentName = preg_replace('#^Pages/#', '', $fileInfo['relative_path']);
        $componentName = str_replace('.vue', '', $componentName);

        return in_array($componentName, $this->inertiaRenders);
    }

    protected function getInertiaRenders(): array
    {
        $renders = [];

        // Search in all PHP files (controllers, routes, etc.)
        $searchPaths = [
            app_path(),
            base_path('routes'),
        ];

        foreach ($searchPaths as $searchPath) {
            if (!is_dir($searchPath)) {
                continue;
            }

            $files = File::allFiles($searchPath);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getPathname());

                // Match Inertia::render('ComponentName') or Inertia::render("ComponentName")
                // Also match Inertia\Inertia::render for when using the full namespace
                preg_match_all(
                    "/(?:Inertia::render|Inertia\\\\Inertia::render)\(['\"]([^'\"]+)['\"]/",
                    $content,
                    $matches
                );

                if (!empty($matches[1])) {
                    foreach ($matches[1] as $componentName) {
                        $renders[] = $componentName;
                    }
                }
            }
        }

        return array_unique($renders);
    }

    protected function isEntryPoint(string $filename): bool
    {
        return in_array($filename, $this->entryPointPatterns);
    }

    protected function isAutoLoaded(string $relativePath): bool
    {
        foreach ($this->autoLoadedPatterns as $pattern) {
            if (fnmatch($pattern, $relativePath)) {
                return true;
            }
        }

        return false;
    }

    protected function isExcludedByPattern(string $filename): bool
    {
        $excludePatterns = config('legacy-cleaner.exclude.javascript', []);

        foreach ($excludePatterns as $pattern) {
            if (fnmatch($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }
}
