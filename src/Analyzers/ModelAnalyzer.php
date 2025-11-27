<?php

namespace Codemonkey76\LegacyCleaner\Analyzers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Codemonkey76\LegacyCleaner\Services\CodeSearchService;
use Codemonkey76\LegacyCleaner\Support\UsageResult;
use ReflectionClass;
use ReflectionMethod;

class ModelAnalyzer
{
    protected CodeSearchService $searchService;

    public function __construct(CodeSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function analyze(bool $checkTables = false): UsageResult
    {
        $modelPath = config('legacy-cleaner.paths.models');
        $models = $this->getModels($modelPath);

        $unused = collect();
        $used = collect();

        foreach ($models as $model) {
            $result = $this->analyzeModel($model, $checkTables);

            if ($result['is_unused']) {
                $unused->push($result);
            } else {
                $used->push($result);
            }
        }

        return new UsageResult($unused, $used);
    }

    protected function getModels(string $path): array
    {
        $models = [];

        $files = File::allFiles($path);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->getClassNameFromFile($file->getPathname());

            if ($className && $this->isModel($className)) {
                $models[] = $className;
            }
        }

        return $models;
    }

    protected function analyzeModel(string $model, bool $checkTables): array
    {
        $reflection = new ReflectionClass($model);
        $references = $this->searchService->findReferences($model);

        $relationshipCount = $this->countRelationships($reflection);

        $result = [
            'class' => $model,
            'file' => $reflection->getFileName(),
            'is_unused' => empty($references),
            'references' => count($references),
            'relationship_count' => $relationshipCount,
        ];

        if ($checkTables) {
            try {
                $instance = new $model();
                $tableName = $instance->getTable();
                $tableExists = Schema::hasTable($tableName);

                $result['table_name'] = $tableName;
                $result['table_exists'] = $tableExists;
            } catch (\Throwable $e) {
                $result['table_name'] = 'Unknown';
                $result['table_exists'] = false;
            }
        }

        return $result;
    }

    protected function countRelationships(ReflectionClass $reflection): int
    {
        $relationshipMethods = [
            'hasOne', 'hasMany', 'belongsTo', 'belongsToMany',
            'morphTo', 'morphOne', 'morphMany', 'morphToMany', 'morphedByMany'
        ];

        $count = 0;
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->class !== $reflection->getName()) {
                continue; // Skip inherited methods
            }

            $methodBody = $this->getMethodBody($method);

            foreach ($relationshipMethods as $relMethod) {
                if (str_contains($methodBody, "\$this->$relMethod(")) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    protected function getMethodBody(ReflectionMethod $method): string
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine() - 1;
        $endLine = $method->getEndLine();
        $length = $endLine - $startLine;

        $source = file($filename);
        $body = implode("", array_slice($source, $startLine, $length));

        return $body;
    }

    protected function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        // Extract namespace
        preg_match('/namespace\s+(.+?);/', $content, $namespaceMatches);
        $namespace = $namespaceMatches[1] ?? null;

        // Extract class name
        preg_match('/class\s+(\w+)/', $content, $classMatches);
        $className = $classMatches[1] ?? null;

        if ($namespace && $className) {
            return $namespace . '\\' . $className;
        }

        return null;
    }

    protected function isModel(string $className): bool
    {
        try {
            return is_subclass_of($className, Model::class);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
