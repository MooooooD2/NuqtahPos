<?php

namespace App\Services\Printing\Connectors;

use App\Models\Printer;
use InvalidArgumentException;

class ConnectorFactory
{
    public static function make(Printer $printer): ConnectorInterface
    {
        return match ($printer->connection_type) {
            'network' => new NetworkConnector(
                $printer->ip_address,
                $printer->port,
            ),
            'usb' => new UsbConnector($printer->usb_device),
            'windows' => new WindowsConnector($printer->windows_printer_name),
            default => throw new InvalidArgumentException(
                'Unsupported connection type: ' . $printer->connection_type,
            ),
        };
    }
}
