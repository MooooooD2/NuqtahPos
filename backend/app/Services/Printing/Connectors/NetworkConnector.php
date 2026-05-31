<?php

namespace App\Services\Printing\Connectors;

use Exception;

/**
 * TCP/IP connector for network-attached thermal printers.
 * Most printers listen on port 9100 (ESC/POS default).
 */
class NetworkConnector implements ConnectorInterface
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private string $ip,
        private int $port = 9100,
        private int $timeout = 5,
    ) {}

    public function open(): void
    {
        $this->socket = @fsockopen(
            $this->ip,
            $this->port,
            $errno,
            $errstr,
            $this->timeout,
        );

        if (! $this->socket) {
            throw new Exception(
                "Cannot connect to printer {$this->ip}:{$this->port} — [{$errno}] {$errstr}",
            );
        }

        stream_set_timeout($this->socket, $this->timeout);
        stream_set_blocking($this->socket, true);
    }

    public function send(string $data): void
    {
        if (! $this->socket) {
            throw new Exception('Socket not open');
        }

        $written = fwrite($this->socket, $data);

        if ($written === false) {
            throw new Exception('Failed to send data to printer');
        }

        if ($written !== strlen($data)) {
            throw new Exception(
                "Partial write: sent {$written} of " . strlen($data) . ' bytes',
            );
        }

        fflush($this->socket);
    }

    public function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
