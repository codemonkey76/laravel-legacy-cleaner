<?php

namespace Codemonkey76\LegacyCleaner\Analyzers;

use Codemonkey76\LegacyCleaner\Services\CodeSearchService;
use Codemonkey76\LegacyCleaner\Support\UsageResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class ControllerAnalyzer
{
    protected CodeSearchService $searchService;

    public function __construct(CodeSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function analyze(): UsageResult
    {
        $controllerPath = config('legacy-cleaner.paths.controllers');
        $controllers = $this->getControllers($controllerPath);

        $unused = collect();
        $used = collect();

        foreach ($controllers as $controller) {
            $result = $this->analyzeController($controller);

            if ($result === null) {
                continue; // Skip controllers that can't be analyzed
            }

            if ($result['is_unused']) {
                $unused->push($result);
            } else {
                $used->push($result);
            }
        }

        return new UsageResult($unused, $used);
    }

    protected function getControllers(string $path): array
    {
        $controllers = [];

        $files = File::allFiles($path);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->getClassNameFromFile($file->getPathname());

            if ($className && $this->isController($className)) {
                $controllers[] = $className;
            }
        }

        return $controllers;
    }

    protected function analyzeController(string $controller): ?array
    {
        try {
            $reflection = new ReflectionClass($controller);
        } catch (\ReflectionException $_e) {
            // Skip controllers that can't be loaded (syntax errors, missing dependencies, etc.)
            return null;
        }

        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        $usedInRoutes = $this->isUsedInRoutes($controller);
        $references = $this->searchService->findReferences($controller);

        $unusedMethods = [];
        foreach ($methods as $method) {
            if ($this->isSystemMethod($method->getName())) {
                continue;
            }

            if (!$this->isMethodUsedInRoutes($controller, $method->getName())) {
                $unusedMethods[] = $method->getName();
            }
        }

        return [
            'class' => $controller,
            'file' => $reflection->getFileName(),
            'is_unused' => !$usedInRoutes && empty($references),
            'used_in_routes' => $usedInRoutes,
            'references' => count($references),
            'unused_methods' => $unusedMethods,
        ];
    }

    protected function isUsedInRoutes(string $controller): bool
    {
        $routes = Route::getRoutes();

        foreach ($routes as $route) {
            $action = $route->getActionName();

            if (Str::contains($action, $controller)) {
                return true;
            }
        }

        return false;
    }

    protected function isMethodUsedInRoutes(string $controller, string $method): bool
    {
        $routes = Route::getRoutes();

        foreach ($routes as $route) {
            $action = $route->getAction('controller');

            if (
                $action === "$controller@$method" ||
                $action === [$controller, $method]
            ) {
                return true;
            }
        }

        return false;
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

    protected function isController(string $className): bool
    {
        return Str::endsWith($className, 'Controller');
    }

    protected function isSystemMethod(string $methodName): bool
    {
        $systemMethods = [
            '__construct',
            '__destruct',
            '__call',
            '__callStatic',
            '__get',
            '__set',
            '__isset',
            '__unset',
            '__sleep',
            '__wakeup',
            '__toString',
            '__invoke',
            'middleware',
            'authorize',
        ];

        return in_array($methodName, $systemMethods);
    }
}
