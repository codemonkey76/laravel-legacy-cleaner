<?php

namespace Codemonkey76\LegacyCleaner\Commands;

use Illuminate\Console\Command;
use Codemonkey76\LegacyCleaner\Analyzers\ControllerAnalyzer;

class AnalyzeControllersCommand extends Command
{
    protected $signature = 'legacy:analyze-controllers 
                            {--output= : Output file path}
                            {--format=table : Output format (table, json, csv)}
                            {--show-methods : Show unused methods for each controller}';

    protected $description = 'Analyze controllers to find unused ones and unused methods';

    protected ControllerAnalyzer $analyzer;

    public function __construct(ControllerAnalyzer $analyzer)
    {
        parent::__construct();
        $this->analyzer = $analyzer;
    }

    public function handle()
    {
        $this->info('Analyzing controllers...');

        $results = $this->analyzer->analyze();

        $this->displayResults($results);

        if ($output = $this->option('output')) {
            $this->saveResults($results, $output);
        }

        return Command::SUCCESS;
    }

    protected function displayResults($results)
    {
        $unused = $results->getUnused();
        $used = $results->getUsed();

        // Summary
        $this->info("\n📊 Summary:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Controllers', $results->getTotalCount()],
                ['Used Controllers', $results->getUsedCount()],
                ['Unused Controllers', $results->getUnusedCount()],
                ['Unused Percentage', $results->getTotalCount() > 0
                    ? round(($results->getUnusedCount() / $results->getTotalCount()) * 100, 2) . '%'
                    : '0%'],
            ]
        );

        if ($unused->isEmpty()) {
            $this->info("\n✅ All controllers appear to be in use!");
            return;
        }

        $this->warn("\n⚠️  Found {$unused->count()} potentially unused controllers:");

        $tableData = $unused->map(function ($controller) {
            return [
                'Class' => class_basename($controller['class']),
                'Full Class' => $controller['class'],
                'File' => str_replace(base_path(), '', $controller['file']),
                'References' => $controller['references'],
            ];
        })->toArray();

        $this->table(
            ['Class', 'Full Class', 'File', 'References'],
            $tableData
        );

        // Show controllers with unused methods
        if ($this->option('show-methods')) {
            $this->showUnusedMethods($used);
        }
    }

    protected function showUnusedMethods($controllers)
    {
        $controllersWithUnusedMethods = $controllers->filter(function ($controller) {
            return !empty($controller['unused_methods']);
        });

        if ($controllersWithUnusedMethods->isEmpty()) {
            $this->info("\n✅ No unused methods found in used controllers!");
            return;
        }

        $this->warn("\n⚠️  Controllers with unused methods:");

        foreach ($controllersWithUnusedMethods as $controller) {
            $this->line("\n📄 " . class_basename($controller['class']));
            $this->line("   File: " . str_replace(base_path(), '', $controller['file']));
            $this->line("   Unused methods:");

            foreach ($controller['unused_methods'] as $method) {
                $this->line("      • " . $method);
            }
        }
    }

    protected function saveResults($results, $path)
    {
        $format = $this->option('format');

        $content = match ($format) {
            'json' => $results->toJson(),
            'csv' => $this->resultsToCSV($results),
            default => $results->toJson(),
        };

        file_put_contents($path, $content);
        $this->info("\n💾 Results saved to: {$path}");
    }

    protected function resultsToCSV($results): string
    {
        $csv = "Class,File,Used in Routes,References,Unused Methods\n";

        foreach ($results->getUnused() as $controller) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s"' . "\n",
                $controller['class'],
                $controller['file'],
                $controller['used_in_routes'] ? 'Yes' : 'No',
                $controller['references'],
                implode(', ', $controller['unused_methods'] ?? [])
            );
        }

        return $csv;
    }
}
