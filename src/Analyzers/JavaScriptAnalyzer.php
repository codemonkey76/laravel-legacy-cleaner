<?php

namespace Codemonkey76\LegacyCleaner\Analyzers;

use Codemonkey76\LegacyCleaner\Services\CodeSearchService;
use Codemonkey76\LegacyCleaner\Support\UsageResult;

class JavaScriptAnalyzer
{
    protected CodeSearchService $searchService;

    // Files that are typically entry points and should be excluded
    protected array $entryPointPatterns = [
        'app.js',
        'app.ts',
        'main.js',
        'main.ts',
        'bootstrap.js',
        'bootstrap.ts',
        'vite.config.js',
        'vite.config.ts',
        'webpack.mix.js',
    ];

    // Patterns for files that are auto-loaded (like pages in some frameworks)
    protected array $autoLoadedPatterns = [
        '*/Pages/*',
        '*/pages/*',
        '*/layouts/*',
        '*/Layouts/*',
    ];

    public function __construct(CodeSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function analyze(string $jsPath): UsageResult
    {
        $jsPath = $jsPath ?? resource_path('js');

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

        // Skip auto-loaded files (like Inertia pages)
        if ($this->isAutoLoaded($fileInfo['relative_path'])) {
            return array_merge($fileInfo, [
                'is_unused' => false,
                'references' => -1,
                'reason' => 'Auto-loaded file (Pages/Layouts)',
            ]);
        }

        // Find references
        $references = $this->findFileReferences($fileInfo, $basePath);

        return array_merge($fileInfo, [
            'is_unused' => $references === 0,
            'references' => $references,
            'reason' => $references === 0 ? 'No imports found' : null,
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
}
