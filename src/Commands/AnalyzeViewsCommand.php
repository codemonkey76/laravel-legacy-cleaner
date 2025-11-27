<?php

namespace Codemonkey76\LegacyCleaner\Commands;

use Illuminate\Console\Command;
use Codemonkey76\LegacyCleaner\Analyzers\ViewAnalyzer;

class AnalyzeViewsCommand extends Command
{
    protected $signature = 'legacy:analyze-views 
                            {--output= : Output file path}
                            {--format=table : Output format (table, json, csv)}
                            {--include-partials : Include partial views in analysis}';

    protected $description = 'Analyze Blade views to find unused ones';

    protected ViewAnalyzer $analyzer;

    public function __construct(ViewAnalyzer $analyzer)
    {
        parent::__construct();
        $this->analyzer = $analyzer;
    }

    public function handle()
    {
        $this->info('Analyzing views...');

        $includePartials = $this->option('include-partials');
        $results = $this->analyzer->analyze($includePartials);

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
                ['Total Views', $results->getTotalCount()],
                ['Used Views', $results->getUsedCount()],
                ['Unused Views', $results->getUnusedCount()],
                ['Unused Percentage', $results->getTotalCount() > 0
                    ? round(($results->getUnusedCount() / $results->getTotalCount()) * 100, 2) . '%'
                    : '0%'],
            ]
        );

        if ($unused->isEmpty()) {
            $this->info("\n✅ All views appear to be in use!");
            return;
        }

        $this->warn("\n⚠️  Found {$unused->count()} potentially unused views:");

        // Group by type
        $layouts = $unused->filter(fn ($v) => $v['type'] === 'layout');
        $partials = $unused->filter(fn ($v) => $v['type'] === 'partial');
        $components = $unused->filter(fn ($v) => $v['type'] === 'component');
        $regular = $unused->filter(fn ($v) => $v['type'] === 'view');

        if ($layouts->isNotEmpty()) {
            $this->displayViewGroup('Layouts', $layouts);
        }

        if ($components->isNotEmpty()) {
            $this->displayViewGroup('Components', $components);
        }

        if ($regular->isNotEmpty()) {
            $this->displayViewGroup('Views', $regular);
        }

        if ($partials->isNotEmpty()) {
            $this->displayViewGroup('Partials', $partials);
        }
    }

    protected function displayViewGroup(string $title, $views)
    {
        $this->newLine();
        $this->line("📁 <comment>{$title}:</comment>");

        $tableData = $views->map(function ($view) {
            return [
                'Name' => $view['name'],
                'Path' => str_replace(resource_path(), '', $view['path']),
                'Size' => $this->formatBytes($view['size']),
                'Modified' => $view['last_modified'],
            ];
        })->toArray();

        $this->table(
            ['Name', 'Path', 'Size', 'Modified'],
            $tableData
        );
    }

    protected function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
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
        $csv = "Name,Type,Path,Size,Last Modified,References\n";

        foreach ($results->getUnused() as $view) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s"' . "\n",
                $view['name'],
                $view['type'],
                $view['path'],
                $view['size'],
                $view['last_modified'],
                $view['references'] ?? 0
            );
        }

        return $csv;
    }
}
