<?php

namespace Codemonkey76\LegacyCleaner\Commands;

use Illuminate\Console\Command;
use Codemonkey76\LegacyCleaner\Analyzers\ModelAnalyzer;

class AnalyzeModelsCommand extends Command
{
    protected $signature = 'legacy:analyze-models 
                            {--output= : Output file path}
                            {--format=table : Output format (table, json, csv)}
                            {--check-tables : Also check if database tables exist}';

    protected $description = 'Analyze Eloquent models to find unused ones';

    protected ModelAnalyzer $analyzer;

    public function __construct(ModelAnalyzer $analyzer)
    {
        parent::__construct();
        $this->analyzer = $analyzer;
    }

    public function handle()
    {
        $this->info('Analyzing models...');

        $checkTables = $this->option('check-tables');
        $results = $this->analyzer->analyze($checkTables);

        $this->displayResults($results, $checkTables);

        if ($output = $this->option('output')) {
            $this->saveResults($results, $output);
        }

        return Command::SUCCESS;
    }

    protected function displayResults($results, $checkTables)
    {
        $unused = $results->getUnused();
        $used = $results->getUsed();

        // Summary
        $this->info("\n📊 Summary:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Models', $results->getTotalCount()],
                ['Used Models', $results->getUsedCount()],
                ['Unused Models', $results->getUnusedCount()],
                ['Unused Percentage', $results->getTotalCount() > 0
                    ? round(($results->getUnusedCount() / $results->getTotalCount()) * 100, 2) . '%'
                    : '0%'],
            ]
        );

        if ($unused->isEmpty()) {
            $this->info("\n✅ All models appear to be in use!");
            return;
        }

        $this->warn("\n⚠️  Found {$unused->count()} potentially unused models:");

        $headers = ['Class', 'File', 'References', 'Relationships'];

        if ($checkTables) {
            $headers[] = 'Table Exists';
        }

        $tableData = $unused->map(function ($model) use ($checkTables) {
            $row = [
                'Class' => class_basename($model['class']),
                'File' => str_replace(base_path(), '', $model['file']),
                'References' => $model['references'],
                'Relationships' => $model['relationship_count'] ?? 0,
            ];

            if ($checkTables) {
                $tableExists = $model['table_exists'] ?? false;
                $row['Table Exists'] = $tableExists ? '✓' : '✗';
            }

            return $row;
        })->toArray();

        $this->table($headers, $tableData);

        // Show models with missing tables
        if ($checkTables) {
            $this->showModelsWithMissingTables($results);
        }
    }

    protected function showModelsWithMissingTables($results)
    {
        $modelsWithMissingTables = $results->getUsed()->filter(function ($model) {
            return isset($model['table_exists']) && !$model['table_exists'];
        });

        if ($modelsWithMissingTables->isEmpty()) {
            return;
        }

        $this->warn("\n⚠️  Used models with missing tables:");

        foreach ($modelsWithMissingTables as $model) {
            $this->line("   • " . $model['class'] . " (expects table: {$model['table_name']})");
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
        $csv = "Class,File,References,Relationships,Table Name,Table Exists\n";

        foreach ($results->getUnused() as $model) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s"' . "\n",
                $model['class'],
                $model['file'],
                $model['references'],
                $model['relationship_count'] ?? 0,
                $model['table_name'] ?? 'N/A',
                isset($model['table_exists']) && $model['table_exists'] ? 'Yes' : 'No'
            );
        }

        return $csv;
    }
}
