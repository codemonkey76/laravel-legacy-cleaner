<?php

namespace Codemonkey76\LegacyCleaner\Commands;

use Illuminate\Console\Command;
use Codemonkey76\LegacyCleaner\Analyzers\RouteAnalyzer;
use Codemonkey76\LegacyCleaner\Analyzers\ControllerAnalyzer;
use Codemonkey76\LegacyCleaner\Services\ReportGenerator;

class GenerateReportCommand extends Command
{
    protected $signature = 'legacy:report 
                            {--format=html : Report format (html, json, markdown)}
                            {--output= : Output file path}';

    protected $description = 'Generate a comprehensive legacy code report';

    public function handle(
        RouteAnalyzer $routeAnalyzer,
        ControllerAnalyzer $controllerAnalyzer,
        ReportGenerator $reportGenerator
    ) {
        $this->info('Generating legacy code report...');

        $this->task('Analyzing routes', function () use ($routeAnalyzer, &$routeResults) {
            $routeResults = $routeAnalyzer->analyze(\Illuminate\Support\Facades\Route::getRoutes());
        });

        $this->task('Analyzing controllers', function () use ($controllerAnalyzer, &$controllerResults) {
            $controllerResults = $controllerAnalyzer->analyze();
        });

        $format = $this->option('format');
        $outputPath = $this->option('output')
            ?? storage_path("app/legacy-cleaner/report-" . date('Y-m-d-His') . ".$format");

        $report = $reportGenerator->generate([
            'routes' => $routeResults,
            'controllers' => $controllerResults,
        ], $format);

        file_put_contents($outputPath, $report);

        $this->info("Report generated: $outputPath");

        return Command::SUCCESS;
    }
}
