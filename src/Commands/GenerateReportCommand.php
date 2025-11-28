<?php

namespace Codemonkey76\LegacyCleaner\Commands;

use Illuminate\Console\Command;
use Codemonkey76\LegacyCleaner\Analyzers\ControllerAnalyzer;
use Codemonkey76\LegacyCleaner\Analyzers\JavaScriptAnalyzer;
use Codemonkey76\LegacyCleaner\Analyzers\ModelAnalyzer;
use Codemonkey76\LegacyCleaner\Analyzers\RouteAnalyzer;
use Codemonkey76\LegacyCleaner\Analyzers\ViewAnalyzer;
use Codemonkey76\LegacyCleaner\Services\ReportGenerator;

class GenerateReportCommand extends Command
{
    protected $signature = 'legacy:report 
                            {--format=html : Report format (html, json, markdown)}
                            {--output= : Output file path}
                            {--skip=* : Skip specific analyzers (routes, controllers, models, views, javascript)}';

    protected $description = 'Generate a comprehensive legacy code report';

    public function handle(
        RouteAnalyzer $routeAnalyzer,
        ControllerAnalyzer $controllerAnalyzer,
        ModelAnalyzer $modelAnalyzer,
        ViewAnalyzer $viewAnalyzer,
        JavaScriptAnalyzer $javascriptAnalyzer,
        ReportGenerator $reportGenerator
    ) {
        $this->info('Generating legacy code report...');

        $skip = $this->option('skip');
        $results = [];

        // Analyze routes
        if (!in_array('routes', $skip)) {
            $this->line('Analyzing routes...');
            $results['routes'] = $routeAnalyzer->analyze(\Illuminate\Support\Facades\Route::getRoutes());
        }

        // Analyze controllers
        if (!in_array('controllers', $skip)) {
            $this->line('Analyzing controllers...');
            $results['controllers'] = $controllerAnalyzer->analyze();
        }

        // Analyze models
        if (!in_array('models', $skip)) {
            $this->line('Analyzing models...');
            $results['models'] = $modelAnalyzer->analyze();
        }

        // Analyze views
        if (!in_array('views', $skip)) {
            $this->line('Analyzing views...');
            $results['views'] = $viewAnalyzer->analyze();
        }

        // Analyze JavaScript/TypeScript
        if (!in_array('javascript', $skip)) {
            $this->line('Analyzing JavaScript/TypeScript files...');
            $results['javascript'] = $javascriptAnalyzer->analyze();
        }

        $format = $this->option('format');
        $outputPath = $this->option('output')
            ?? storage_path("app/legacy-cleaner/report-" . date('Y-m-d-His') . ".$format");

        // Ensure directory exists
        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $report = $reportGenerator->generate($results, $format);

        file_put_contents($outputPath, $report);

        $this->info("Report generated: $outputPath");

        // Show quick summary
        $this->newLine();
        $this->info('📊 Quick Summary:');
        foreach ($results as $type => $result) {
            $total = $result->getTotalCount();
            $unused = $result->getUnusedCount();
            $percentage = $total > 0 ? round(($unused / $total) * 100, 1) : 0;

            $emoji = $percentage > 50 ? '🔴' : ($percentage > 20 ? '🟡' : '🟢');
            $this->line("  {$emoji} " . ucfirst($type) . ": {$unused}/{$total} unused ({$percentage}%)");
        }

        return Command::SUCCESS;
    }
}
