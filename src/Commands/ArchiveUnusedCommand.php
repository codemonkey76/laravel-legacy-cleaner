<?php

namespace Codemonkey76\LegacyCleaner\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Codemonkey76\LegacyCleaner\Analyzers\ControllerAnalyzer;

class ArchiveUnusedCommand extends Command
{
    protected $signature = 'legacy:archive 
                            {--dry-run : Show what would be archived without actually doing it}
                            {--type=* : Types to archive (controllers, routes, views)}';

    protected $description = 'Archive unused code to a separate directory';

    public function handle(ControllerAnalyzer $controllerAnalyzer)
    {
        if (!config('legacy-cleaner.archive.enabled')) {
            $this->error('Archiving is disabled in config');
            return Command::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $archivePath = config('legacy-cleaner.archive.path');

        if (!$dryRun) {
            if (!$this->confirm('This will move unused files to the archive. Continue?')) {
                return Command::CANCELLED;
            }
        }

        $this->info('Analyzing unused code...');

        $controllerResults = $controllerAnalyzer->analyze();
        $unusedControllers = $controllerResults->getUnused();

        if ($unusedControllers->isEmpty()) {
            $this->info('No unused controllers found!');
            return Command::SUCCESS;
        }

        $this->warn("Found {$unusedControllers->count()} unused controllers");

        foreach ($unusedControllers as $controller) {
            $originalPath = $controller['file'];
            $relativePath = str_replace(app_path(), '', $originalPath);
            $archiveFilePath = $archivePath . $relativePath;

            if ($dryRun) {
                $this->line("Would archive: $originalPath → $archiveFilePath");
            } else {
                File::ensureDirectoryExists(dirname($archiveFilePath));
                File::move($originalPath, $archiveFilePath);
                $this->info("Archived: {$controller['class']}");
            }
        }

        if (!$dryRun) {
            $this->info("Archived {$unusedControllers->count()} files to: $archivePath");
        }

        return Command::SUCCESS;
    }
}
