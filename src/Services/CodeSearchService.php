<?php

namespace Codemonkey76\LegacyCleaner\Services;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class CodeSearchService
{
    public function searchInDirectory(string $directory, string $pattern): int
    {
        if (!File::exists($directory)) {
            return 0;
        }

        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        $files = new RegexIterator(
            $iterator,
            '/^.+\.(php|vue|js|ts|blade\.php)$/i',
            RegexIterator::GET_MATCH
        );

        foreach ($files as $file) {
            $filePath = $file[0];
            $content = file_get_contents($filePath);

            if (preg_match_all("/$pattern/", $content, $matches)) {
                $count += count($matches[0]);
            }
        }

        return $count;
    }

    public function searchInFile(string $filePath, string $pattern): int
    {
        if (!File::exists($filePath)) {
            return 0;
        }

        $content = file_get_contents($filePath);
        preg_match_all("/$pattern/", $content, $matches);

        return count($matches[0]);
    }

    public function findReferences(string $className): array
    {
        $references = [];
        $searchPaths = [
            app_path(),
            resource_path(),
            base_path('routes'),
        ];

        foreach ($searchPaths as $path) {
            $found = $this->searchForClass($path, $className);
            $references = array_merge($references, $found);
        }

        return $references;
    }

    protected function searchForClass(string $directory, string $className): array
    {
        $found = [];
        $shortName = class_basename($className);

        $patterns = [
            "use $className;",
            "$shortName::class",
            "new $shortName",
            "instanceof $shortName",
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        $files = new RegexIterator(
            $iterator,
            '/^.+\.php$/i',
            RegexIterator::GET_MATCH
        );

        foreach ($files as $file) {
            $filePath = $file[0];
            $content = file_get_contents($filePath);

            foreach ($patterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    $found[] = [
                        'file' => $filePath,
                        'pattern' => $pattern,
                    ];
                    break;
                }
            }
        }

        return $found;
    }
}
