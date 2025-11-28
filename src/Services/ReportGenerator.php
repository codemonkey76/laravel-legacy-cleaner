<?php

namespace Codemonkey76\LegacyCleaner\Services;

use Codemonkey76\LegacyCleaner\Support\UsageResult;

class ReportGenerator
{
    public function generate(array $results, string $format = 'html'): string
    {
        return match ($format) {
            'html' => $this->generateHtml($results),
            'json' => $this->generateJson($results),
            'markdown' => $this->generateMarkdown($results),
            default => $this->generateJson($results),
        };
    }

    protected function generateJson(array $results): string
    {
        $data = [];

        foreach ($results as $type => $result) {
            if ($result instanceof UsageResult) {
                $data[$type] = $result->toArray();
            }
        }

        return json_encode([
            'generated_at' => now()->toIso8601String(),
            'results' => $data,
        ], JSON_PRETTY_PRINT);
    }

    protected function generateMarkdown(array $results): string
    {
        $markdown = "# Legacy Code Analysis Report\n\n";
        $markdown .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";

        // Summary table
        $markdown .= "## Overall Summary\n\n";
        $markdown .= "| Category | Total | Used | Unused | % Unused |\n";
        $markdown .= "|----------|-------|------|--------|----------|\n";

        foreach ($results as $type => $result) {
            if (!$result instanceof UsageResult) {
                continue;
            }

            $total = $result->getTotalCount();
            $unused = $result->getUnusedCount();
            $percentage = $total > 0 ? round(($unused / $total) * 100, 2) : 0;

            $markdown .= "| " . ucfirst($type) . " | {$total} | {$result->getUsedCount()} | {$unused} | {$percentage}% |\n";
        }

        $markdown .= "\n";

        // Details for each category
        foreach ($results as $type => $result) {
            if (!$result instanceof UsageResult) {
                continue;
            }

            $markdown .= "## " . ucfirst($type) . "\n\n";

            if ($result->getUnusedCount() > 0) {
                $markdown .= "### Unused Items\n\n";

                foreach ($result->getUnused() as $item) {
                    $markdown .= $this->formatMarkdownItem($type, $item);
                }
            } else {
                $markdown .= "✅ All " . $type . " are in use!\n\n";
            }
        }

        return $markdown;
    }

    protected function formatMarkdownItem(string $type, array $item): string
    {
        $markdown = "";

        switch ($type) {
            case 'routes':
                $markdown .= "- **" . ($item['name'] ?? 'Unnamed') . "**\n";
                $markdown .= "  - URI: `" . $item['uri'] . "`\n";
                $markdown .= "  - Method: `" . $item['method'] . "`\n";
                $markdown .= "  - Action: `" . $item['action'] . "`\n\n";
                break;

            case 'javascript':
                $markdown .= "- **" . $item['name'] . "**\n";
                $markdown .= "  - Path: `" . $item['relative_path'] . "`\n";
                $markdown .= "  - Type: " . strtoupper($item['extension']) . "\n";
                $markdown .= "  - Size: " . $this->formatBytes($item['size']) . "\n\n";
                break;

            case 'views':
                $markdown .= "- **" . $item['name'] . "**\n";
                $markdown .= "  - Type: " . ucfirst($item['type']) . "\n";
                $markdown .= "  - Size: " . $this->formatBytes($item['size']) . "\n\n";
                break;

            default:
                $markdown .= "- **" . ($item['class'] ?? $item['name'] ?? 'Unknown') . "**\n";
                if (isset($item['file'])) {
                    $markdown .= "  - File: `" . $item['file'] . "`\n";
                }
                $markdown .= "  - References: " . ($item['references'] ?? 0) . "\n\n";
                break;
        }

        return $markdown;
    }

    protected function generateHtml(array $results): string
    {
        $html = $this->getHtmlHeader();

        $html .= "<div class='container'>";
        $html .= "<h1>Legacy Code Analysis Report</h1>";
        $html .= "<p class='timestamp'>Generated: " . now()->format('Y-m-d H:i:s') . "</p>";

        // Overall summary
        $html .= $this->generateOverallSummary($results);

        // Individual sections
        foreach ($results as $type => $result) {
            if (!$result instanceof UsageResult) {
                continue;
            }

            $html .= $this->generateHtmlSection($type, $result);
        }

        $html .= "</div>";
        $html .= $this->getHtmlFooter();

        return $html;
    }

    protected function generateOverallSummary(array $results): string
    {
        $html = "<div class='overall-summary'>";
        $html .= "<h2>Overall Summary</h2>";
        $html .= "<table>";
        $html .= "<thead><tr>";
        $html .= "<th>Category</th>";
        $html .= "<th>Total</th>";
        $html .= "<th>Used</th>";
        $html .= "<th>Unused</th>";
        $html .= "<th>% Unused</th>";
        $html .= "</tr></thead>";
        $html .= "<tbody>";

        foreach ($results as $type => $result) {
            if (!$result instanceof UsageResult) {
                continue;
            }

            $total = $result->getTotalCount();
            $unused = $result->getUnusedCount();
            $percentage = $total > 0 ? round(($unused / $total) * 100, 2) : 0;

            $statusClass = $percentage > 50 ? 'danger' : ($percentage > 20 ? 'warning' : 'success');

            $html .= "<tr>";
            $html .= "<td><strong>" . ucfirst($type) . "</strong></td>";
            $html .= "<td>{$total}</td>";
            $html .= "<td class='success'>{$result->getUsedCount()}</td>";
            $html .= "<td class='{$statusClass}'>{$unused}</td>";
            $html .= "<td class='{$statusClass}'>{$percentage}%</td>";
            $html .= "</tr>";
        }

        $html .= "</tbody></table>";
        $html .= "</div>";

        return $html;
    }

    protected function generateHtmlSection(string $type, UsageResult $result): string
    {
        $html = "<div class='section'>";
        $html .= "<h2>" . ucfirst($type) . "</h2>";

        // Summary
        $html .= "<div class='summary'>";
        $html .= "<h3>Summary</h3>";
        $html .= "<table>";
        $html .= "<tr><th>Total</th><td>" . $result->getTotalCount() . "</td></tr>";
        $html .= "<tr><th>Used</th><td class='success'>" . $result->getUsedCount() . "</td></tr>";
        $html .= "<tr><th>Unused</th><td class='warning'>" . $result->getUnusedCount() . "</td></tr>";
        $html .= "<tr><th>Unused %</th><td>" . ($result->getTotalCount() > 0
            ? round(($result->getUnusedCount() / $result->getTotalCount()) * 100, 2)
            : 0) . "%</td></tr>";
        $html .= "</table>";
        $html .= "</div>";

        // Unused items
        if ($result->getUnusedCount() > 0) {
            $html .= "<div class='unused-items'>";
            $html .= "<h3>Unused Items</h3>";
            $html .= $this->generateTableForType($type, $result->getUnused());
            $html .= "</div>";
        }

        $html .= "</div>";

        return $html;
    }

    protected function generateTableForType(string $type, $items): string
    {
        $html = "<table>";

        switch ($type) {
            case 'routes':
                $html .= "<thead><tr>";
                $html .= "<th>Route Name</th><th>URI</th><th>Method</th><th>Action</th>";
                $html .= "</tr></thead><tbody>";

                foreach ($items as $item) {
                    $html .= "<tr>";
                    $html .= "<td><code>" . htmlspecialchars($item['name'] ?? 'Unnamed') . "</code></td>";
                    $html .= "<td><small>" . htmlspecialchars($item['uri'] ?? '') . "</small></td>";
                    $html .= "<td>" . htmlspecialchars($item['method'] ?? '') . "</td>";
                    $html .= "<td><small>" . htmlspecialchars($item['action'] ?? '') . "</small></td>";
                    $html .= "</tr>";
                }
                break;

            case 'javascript':
                $html .= "<thead><tr>";
                $html .= "<th>File</th><th>Path</th><th>Type</th><th>Size</th><th>Modified</th>";
                $html .= "</tr></thead><tbody>";

                foreach ($items as $item) {
                    $html .= "<tr>";
                    $html .= "<td><code>" . htmlspecialchars($item['name']) . "</code></td>";
                    $html .= "<td><small>" . htmlspecialchars($item['relative_path']) . "</small></td>";
                    $html .= "<td>" . strtoupper($item['extension']) . "</td>";
                    $html .= "<td>" . $this->formatBytes($item['size']) . "</td>";
                    $html .= "<td><small>" . htmlspecialchars($item['modified']) . "</small></td>";
                    $html .= "</tr>";
                }
                break;

            case 'views':
                $html .= "<thead><tr>";
                $html .= "<th>Name</th><th>Type</th><th>Size</th><th>Modified</th>";
                $html .= "</tr></thead><tbody>";

                foreach ($items as $item) {
                    $html .= "<tr>";
                    $html .= "<td><code>" . htmlspecialchars($item['name']) . "</code></td>";
                    $html .= "<td>" . ucfirst($item['type']) . "</td>";
                    $html .= "<td>" . $this->formatBytes($item['size']) . "</td>";
                    $html .= "<td><small>" . htmlspecialchars($item['last_modified']) . "</small></td>";
                    $html .= "</tr>";
                }
                break;

            default:
                $html .= "<thead><tr>";
                $html .= "<th>Item</th><th>File</th><th>References</th>";
                $html .= "</tr></thead><tbody>";

                foreach ($items as $item) {
                    $html .= "<tr>";
                    $html .= "<td><code>" . htmlspecialchars($item['class'] ?? $item['name'] ?? 'Unknown') . "</code></td>";
                    $html .= "<td><small>" . htmlspecialchars($item['file'] ?? 'N/A') . "</small></td>";
                    $html .= "<td>" . ($item['references'] ?? 0) . "</td>";
                    $html .= "</tr>";
                }
                break;
        }

        $html .= "</tbody></table>";
        return $html;
    }

    protected function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    protected function getHtmlHeader(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legacy Code Analysis Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        h2 {
            color: #34495e;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        h3 {
            color: #7f8c8d;
            margin: 20px 0 10px;
        }
        .timestamp {
            color: #95a5a6;
            margin-bottom: 30px;
        }
        .section {
            margin: 40px 0;
        }
        .overall-summary {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .summary {
            background: #ecf0f1;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #34495e;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .success {
            color: #27ae60;
            font-weight: 600;
        }
        .warning {
            color: #f39c12;
            font-weight: 600;
        }
        .danger {
            color: #e74c3c;
            font-weight: 600;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        small {
            color: #7f8c8d;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
HTML;
    }

    protected function getHtmlFooter(): string
    {
        return <<<'HTML'
</body>
</html>
HTML;
    }
}
