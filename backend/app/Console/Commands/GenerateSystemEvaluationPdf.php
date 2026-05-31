<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;

class GenerateSystemEvaluationPdf extends Command
{
    protected $signature = 'report:system-evaluation {--out=system-evaluation.pdf : Output filename inside storage/app/}';

    protected $description = 'Generate the system evaluation report as a PDF';

    public function handle(): int
    {
        $filename = $this->option('out');
        $outPath = storage_path('app/' . $filename);

        $this->info('Rendering evaluation report…');

        $pdf = Pdf::loadView('reports.pdf.system-evaluation')
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'dejavu sans');

        $dir = dirname($outPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdf->save($outPath);

        $this->info("PDF saved → {$outPath}");

        return self::SUCCESS;
    }
}
