<?php

namespace App\Services\Printing\Connectors;

use Exception;

/**
 * Windows print-spooler connector.
 * Uses the `copy` command to send raw ESC/POS data via a temp file.
 * Requires the printer to be shared or directly accessible by name.
 */
class WindowsConnector implements ConnectorInterface
{
    private ?string $tempFile = null;

    public function __construct(private string $printerName) {}

    public function open(): void
    {
        if (empty($this->printerName)) {
            throw new Exception('Windows printer name not configured');
        }
        $this->tempFile = tempnam(sys_get_temp_dir(), 'escpos_');
    }

    public function send(string $data): void
    {
        if (! $this->tempFile) {
            throw new Exception('Connector not open');
        }

        file_put_contents($this->tempFile, $data);
    }

    public function close(): void
    {
        if ($this->tempFile && file_exists($this->tempFile)) {
            $escaped = escapeshellarg($this->tempFile);
            $printer = escapeshellarg($this->printerName);
            // Send raw bytes to the Windows printer
            exec("copy /b {$escaped} {$printer}", $output, $code);
            unlink($this->tempFile);
            $this->tempFile = null;

            if ($code !== 0) {
                throw new Exception(
                    "Windows print failed (exit {$code}) for printer: {$this->printerName}",
                );
            }
        }
    }

    public function __destruct()
    {
        if ($this->tempFile && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }
}
