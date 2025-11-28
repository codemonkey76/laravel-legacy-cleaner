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

        foreach ($results as $type => $result) {
            if (!$result instanceof UsageResult) {
                continue;
            }

            $markdown .= "## " . ucfirst($type) . "\n\n";
            $markdown .= "### Summary\n\n";
            $markdown .= "- **Total:** " . $result->getTotalCount() . "\n";
            $markdown .= "- **Used:** " . $result->getUsedCount() . "\n";
            $markdown .= "- **Unused:** " . $result->getUnusedCount() . "\n";
            $markdown .= "- **Unused Percentage:** " . ($result->getTotalCount() > 0
                ? round(($result->getUnusedCount() / $result->getTotalCount()) * 100, 2)
                : 0) . "%\n\n";

            if ($result->getUnusedCount() > 0) {
                $markdown .= "### Unused Items\n\n";

                foreach ($result->getUnused() as $item) {
                    $markdown .= "- **" . ($item['class'] ?? $item['name'] ?? 'Unknown') . "**\n";

                    if (isset($item['file'])) {
                        $markdown .= "  - File: `" . $item['file'] . "`\n";
                    }

                    if (isset($item['uri'])) {
                        $markdown .= "  - URI: `" . $item['uri'] . "`\n";
                    }

                    if (isset($item['method'])) {
                        $markdown .= "  - Method: `" . $item['method'] . "`\n";
                    }

                    if (isset($item['action'])) {
                        $markdown .= "  - Action: `" . $item['action'] . "`\n";
                    }

                    if (isset($item['references'])) {
                        $markdown .= "  - References: " . $item['references'] . "\n";
                    }

                    $markdown .= "\n";
                }
            }
        }

        return $markdown;
    }

    protected function generateHtml(array $results): string
    {
        $html = $this->getHtmlHeader();

        $html .= "<div class='container'>";
        $html .= "<h1>Legacy Code Analysis Report</h1>";
        $html .= "<p class='timestamp'>Generated: " . now()->format('Y-m-d H:i:s') . "</p>";

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
            $html .= "<table>";

            // Different headers for routes vs controllers
            if ($type === 'routes') {
                $html .= "<thead><tr>";
                $html .= "<th>Route Name</th>";
                $html .= "<th>URI</th>";
                $html .= "<th>Method</th>";
                $html .= "<th>Action</th>";
                $html .= "</tr></thead>";
                $html .= "<tbody>";

                foreach ($result->getUnused() as $item) {
                    $html .= "<tr>";
                    $html .= "<td><code>" . htmlspecialchars($item['name'] ?? 'Unnamed') . "</code></td>";
                    $html .= "<td><small>" . htmlspecialchars($item['uri'] ?? '') . "</small></td>";
                    $html .= "<td>" . htmlspecialchars($item['method'] ?? '') . "</td>";
                    $html .= "<td><small>" . htmlspecialchars($item['action'] ?? '') . "</small></td>";
                    $html .= "</tr>";
                }
            } else {
                $html .= "<thead><tr>";
                $html .= "<th>Item</th>";
                $html .= "<th>File</th>";
                $html .= "<th>References</th>";
                $html .= "</tr></thead>";
                $html .= "<tbody>";

                foreach ($result->getUnused() as $item) {
                    $html .= "<tr>";
                    $html .= "<td><code>" . htmlspecialchars($item['class'] ?? $item['name'] ?? 'Unknown') . "</code></td>";
                    $html .= "<td><small>" . htmlspecialchars($item['file'] ?? 'N/A') . "</small></td>";
                    $html .= "<td>" . ($item['references'] ?? 0) . "</td>";
                    $html .= "</tr>";
                }
            }

            $html .= "</tbody></table>";
            $html .= "</div>";
        }

        $html .= "</div>";

        return $html;
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
