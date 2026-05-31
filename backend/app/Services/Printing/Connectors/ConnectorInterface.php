<?php

namespace App\Services\Printing\Connectors;

interface ConnectorInterface
{
    public function open(): void;

    public function send(string $data): void;

    public function close(): void;
}
