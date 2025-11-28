<?php

namespace Codemonkey76\LegacyCleaner\Commands;

use Illuminate\Console\Command;
use Codemonkey76\LegacyCleaner\Analyzers\JavascriptAnalyzer;

class AnalyzeJavaScriptCommand extends Command
{
    protected $signature = 'legacy:analyze-javascript 
                            {--path= : Path to JavaScript directory (default: resources/js)}
                            {--output= : Output file path}
                            {--format=table : Output format (table, json, csv)}
                            {--show-entry-points : Show entry points and auto-loaded files}';

    protected $description = 'Analyze JavaScript/TypeScript files to find unused ones';

    protected JavascriptAnalyzer $analyzer;

    public function __construct(JavaScriptAnalyzer $analyzer)
    {
        parent::__construct();
        $this->analyzer = $analyzer;
    }

    public function handle()
    {
        $this->info('Analyzing JavaScript/TypeScript files...');

        $path = $this->option('path') ?? resource_path('js');
        $results = $this->analyzer->analyze($path);

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
                ['Total Files', $results->getTotalCount()],
                ['Used Files', $results->getUsedCount()],
                ['Unused Files', $results->getUnusedCount()],
                ['Unused Percentage', $results->getTotalCount() > 0
                    ? round(($results->getUnusedCount() / $results->getTotalCount()) * 100, 2) . '%'
                    : '0%'],
            ]
        );

        if ($unused->isEmpty()) {
            $this->info("\n✅ All JavaScript/TypeScript files appear to be in use!");
            return;
        }

        $this->warn("\n⚠️  Found {$unused->count()} potentially unused files:");

        $tableData = $unused->map(function ($file) {
            return [
                'File' => $file['name'],
                'Path' => str_replace(resource_path(), 'resources', dirname($file['path'])),
                'Type' => strtoupper($file['extension']),
                'Size' => $this->formatBytes($file['size']),
                'Modified' => $file['modified'],
            ];
        })->toArray();

        $this->table(
            ['File', 'Path', 'Type', 'Size', 'Modified'],
            $tableData
        );

        // Show entry points if requested
        if ($this->option('show-entry-points')) {
            $entryPoints = $used->filter(fn ($f) => ($f['references'] ?? 0) === -1);

            if ($entryPoints->isNotEmpty()) {
                $this->info("\n📍 Entry Points & Auto-loaded Files:");
                foreach ($entryPoints as $file) {
                    $this->line("   • {$file['relative_path']} - {$file['reason']}");
                }
            }
        }
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
        $csv = "File,Path,Type,Size,Modified,References\n";

        foreach ($results->getUnused() as $file) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s"' . "\n",
                $file['name'],
                $file['relative_path'],
                $file['extension'],
                $file['size'],
                $file['modified'],
                $file['references'] ?? 0
            );
        }

        return $csv;
    }
}
