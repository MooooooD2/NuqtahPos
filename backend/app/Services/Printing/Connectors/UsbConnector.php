<?php

namespace App\Services\Printing\Connectors;

use Exception;

/**
 * USB device-file connector (Linux/Mac: /dev/usb/lp0).
 * The web-server user must have write permission to the device file.
 */
class UsbConnector implements ConnectorInterface
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(private string $device = '/dev/usb/lp0') {}

    public function open(): void
    {
        if (! file_exists($this->device)) {
            throw new Exception("USB device not found: {$this->device}");
        }

        $this->handle = @fopen($this->device, 'wb');

        if (! $this->handle) {
            throw new Exception(
                "Cannot open USB device: {$this->device}. Check permissions.",
            );
        }
    }

    public function send(string $data): void
    {
        if (! $this->handle) {
            throw new Exception('Device not open');
        }

        $written = fwrite($this->handle, $data);

        if ($written === false) {
            throw new Exception('Failed to write to USB device');
        }

        fflush($this->handle);
    }

    public function close(): void
    {
        if ($this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
