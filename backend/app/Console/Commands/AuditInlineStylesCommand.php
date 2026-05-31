<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Scans all Blade templates and reports inline style="" attributes that need
 * to be extracted into external CSS classes before unsafe-inline can be removed
 * from the Content-Security-Policy style-src-attr directive.
 *
 * Usage:
 *   php artisan csp:audit-inline-styles
 *   php artisan csp:audit-inline-styles --output=storage/logs/inline-styles.json
 */
class AuditInlineStylesCommand extends Command
{
    protected $signature = 'csp:audit-inline-styles {--output= : Path to write JSON report}';

    protected $description = 'Report all inline style="" attributes in Blade templates (CSP migration helper)';

    public function handle(): int
    {
        $viewsPath = resource_path('views');
        $finder = Finder::create()->files()->name('*.blade.php')->in($viewsPath);

        $report = [];
        $total = 0;

        foreach ($finder as $file) {
            $content = $file->getContents();
            $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());

            preg_match_all('/style="([^"]+)"/i', $content, $matches, PREG_OFFSET_CAPTURE);

            if (empty($matches[0])) {
                continue;
            }

            $lines = explode("\n", $content);
            $lineMap = [];
            $offset = 0;
            foreach ($lines as $lineNum => $line) {
                $lineMap[$lineNum + 1] = $offset;
                $offset += strlen($line) + 1;
            }

            $occurrences = [];
            foreach ($matches[0] as [$fullMatch, $byteOffset]) {
                // Find which line number this byte offset belongs to
                $lineNumber = 1;
                foreach ($lineMap as $ln => $lo) {
                    if ($lo > $byteOffset) {
                        break;
                    }
                    $lineNumber = $ln;
                }

                $occurrences[] = [
                    'line' => $lineNumber,
                    'style' => $matches[1][count($occurrences)][0],
                ];
                $total++;
            }

            $report[$relative] = [
                'count' => count($occurrences),
                'occurrences' => $occurrences,
            ];
        }

        // Sort by file with most occurrences first
        uasort($report, fn ($a, $b) => $b['count'] <=> $a['count']);

        $this->info("Found {$total} inline style attributes across " . count($report) . " files.\n");

        $this->table(
            ['File', 'Count'],
            collect($report)->map(fn ($data, $file) => [$file, $data['count']])->values()->toArray(),
        );

        $outputPath = $this->option('output');
        if ($outputPath) {
            file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line("\nDetailed report written to: {$outputPath}");
        }

        $this->newLine();
        $this->warn('Next step: move each inline style to a CSS class in public/css/app.css, then set');
        $this->warn("  style-src-attr 'none'  in SecurityHeaders middleware.");

        return self::SUCCESS;
    }
}
