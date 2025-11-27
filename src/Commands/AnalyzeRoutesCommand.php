<?php

namespace Codemonkey76\LegacyCleaner\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Codemonkey76\LegacyCleaner\Analyzers\RouteAnalyzer;

class AnalyzeRoutesCommand extends Command
{
    protected $signature = 'legacy:analyze-routes 
                            {--output= : Output file path}
                            {--format=table : Output format (table, json, csv)}';

    protected $description = 'Analyze routes to find unused ones';

    protected RouteAnalyzer $analyzer;

    public function __construct(RouteAnalyzer $analyzer)
    {
        parent::__construct();
        $this->analyzer = $analyzer;
    }

    public function handle()
    {
        $this->info('Analyzing routes...');

        $routes = Route::getRoutes();
        $results = $this->analyzer->analyze($routes);

        $this->displayResults($results);

        if ($output = $this->option('output')) {
            $this->saveResults($results, $output);
        }

        return Command::SUCCESS;
    }

    protected function displayResults($results)
    {
        $unused = $results->getUnused();

        if ($unused->isEmpty()) {
            $this->info('✅ All routes appear to be in use!');
            return;
        }

        $this->warn("Found {$unused->count()} potentially unused routes:");

        $tableData = $unused->map(function ($route) {
            return [
                'Name' => $route['name'] ?? 'N/A',
                'URI' => $route['uri'],
                'Method' => $route['method'],
                'Action' => $route['action'],
            ];
        })->toArray();

        $this->table(
            ['Name', 'URI', 'Method', 'Action'],
            $tableData
        );
    }

    protected function saveResults($results, $path)
    {
        file_put_contents($path, $results->toJson());
        $this->info("Results saved to: {$path}");
    }
}
