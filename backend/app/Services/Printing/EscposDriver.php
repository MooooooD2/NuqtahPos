<?php

namespace App\Services\Printing;

/**
 * Custom ESC/POS driver — no external packages required.
 * Handles Arabic text encoding (UTF-8 → CP720), QR codes,
 * barcodes, text formatting, and paper cutting.
 */
class EscposDriver
{
    private string $buffer = '';

    private int $charsPerLine = 48;

    private string $charSet = 'CP720';

    // ESC/POS Command Constants
    const ESC = "\x1B";

    const GS = "\x1D";

    const FS = "\x1C";

    const LF = "\x0A";

    const CR = "\x0D";

    public function __construct(int $charsPerLine = 48, string $charSet = 'CP720')
    {
        $this->charsPerLine = $charsPerLine;
        $this->charSet = $charSet;
    }

    // ── Initialization ─────────────────────────────────────────────────────────

    public function initialize(): self
    {
        $this->buffer .= self::ESC . '@'; // Initialize printer
        $this->selectCharacterSet();

        return $this;
    }

    private function selectCharacterSet(): void
    {
        $table = match ($this->charSet) {
            'CP437' => 0,
            'CP720' => 18,
            'UTF-8' => 0,
            default => 0,
        };
        $this->buffer .= self::ESC . 't' . chr($table);

        if ($this->charSet === 'CP720') {
            $this->buffer .= self::FS . '.';       // Select Arabic mode
            $this->buffer .= self::FS . 'q' . chr(1); // Enable Arabic character shaping
        }
    }

    // ── Text Output ────────────────────────────────────────────────────────────

    public function text(string $text): self
    {
        $this->buffer .= $this->encodeText($text);

        return $this;
    }

    public function line(string $text = ''): self
    {
        if ($text !== '') {
            $this->text($text);
        }
        $this->buffer .= self::LF;

        return $this;
    }

    public function centeredText(string $text): self
    {
        $this->setJustification('center');
        $this->text($text);
        $this->setJustification('left');
        $this->buffer .= self::LF;

        return $this;
    }

    public function rightText(string $text): self
    {
        $this->setJustification('right');
        $this->text($text);
        $this->setJustification('left');
        $this->buffer .= self::LF;

        return $this;
    }

    public function boldText(string $text): self
    {
        $this->setBold(true);
        $this->text($text);
        $this->setBold(false);

        return $this;
    }

    public function boldLine(string $text = ''): self
    {
        $this->setBold(true);
        $this->line($text);
        $this->setBold(false);

        return $this;
    }

    public function doubleHeightText(string $text): self
    {
        $this->setDoubleHeight(true);
        $this->text($text);
        $this->setDoubleHeight(false);

        return $this;
    }

    // ── Layout Helpers ─────────────────────────────────────────────────────────

    public function separator(string $char = '-'): self
    {
        $this->line(str_repeat($char, $this->charsPerLine));

        return $this;
    }

    public function dashedLine(): self
    {
        $this->line(str_repeat('- ', (int) ($this->charsPerLine / 2)));

        return $this;
    }

    public function twoColumnText(string $left, string $right): self
    {
        $leftLen = mb_strlen($left, 'UTF-8');
        $rightLen = mb_strlen($right, 'UTF-8');
        $spaces = max(1, $this->charsPerLine - $leftLen - $rightLen);
        $this->line($left . str_repeat(' ', $spaces) . $right);

        return $this;
    }

    public function threeColumnText(string $left, string $center, string $right): self
    {
        $col1 = 16;
        $col2 = 10;
        $col3 = $this->charsPerLine - $col1 - $col2;
        $line = str_pad(mb_substr($left, 0, $col1), $col1)
              . str_pad(mb_substr($center, 0, $col2), $col2)
              . str_pad($right, $col3, ' ', STR_PAD_LEFT);
        $this->line($line);

        return $this;
    }

    public function itemRow(string $name, int $qty, string $price, string $subtotal): self
    {
        $nameWidth = $this->charsPerLine - 20;
        $truncated = mb_substr($name, 0, $nameWidth);
        $padded = str_pad($truncated, $nameWidth);
        $qtyStr = str_pad('x' . $qty, 5);
        $priceStr = str_pad($price, 7);
        $totalStr = str_pad($subtotal, 8, ' ', STR_PAD_LEFT);
        $this->line($padded . $qtyStr . $priceStr . $totalStr);

        return $this;
    }

    // ── Barcodes & QR ──────────────────────────────────────────────────────────

    public function barcode(string $data, string $type = 'CODE128', int $height = 60): self
    {
        $typeCode = match (strtoupper($type)) {
            'CODE128' => 73,
            'EAN13' => 67,
            'CODE39' => 65,
            'UPC-A' => 65,
            default => 73,
        };
        // GS k m n d1…dk
        $this->buffer .= self::GS . 'k' . chr($typeCode) . chr(strlen($data)) . $data;
        $this->buffer .= self::LF;

        return $this;
    }

    public function qrCode(string $data, int $size = 4, string $ecLevel = 'M'): self
    {
        $ecCode = match ($ecLevel) {
            'L' => 48, 'M' => 49, 'Q' => 50, 'H' => 51,
            default => 49,
        };
        // Select QR model 2
        $this->buffer .= self::GS . '(k' . chr(2) . chr(0) . chr(49) . chr(65) . chr(50);
        // Set size
        $this->buffer .= self::GS . '(k' . chr(3) . chr(0) . chr(49) . chr(67) . chr($size);
        // Set error correction
        $this->buffer .= self::GS . '(k' . chr(3) . chr(0) . chr(49) . chr(69) . chr($ecCode);
        // Store data
        $len = strlen($data) + 3;
        $this->buffer .= self::GS . '(k'
            . chr($len % 256) . chr((int) ($len / 256))
            . chr(49) . chr(80) . chr(48) . $data;
        // Print
        $this->buffer .= self::GS . '(k' . chr(3) . chr(0) . chr(49) . chr(81) . chr(48);
        $this->buffer .= self::LF;

        return $this;
    }

    // ── Hardware Control ───────────────────────────────────────────────────────

    public function cut(bool $partial = false): self
    {
        $mode = $partial ? chr(1) : chr(0);
        $this->buffer .= self::GS . 'V' . $mode;

        return $this;
    }

    public function openCashDrawer(): self
    {
        // ESC p m t1 t2
        $this->buffer .= self::ESC . 'p' . chr(0) . chr(50) . chr(50);

        return $this;
    }

    public function feed(int $lines = 3): self
    {
        $this->buffer .= self::ESC . 'd' . chr($lines);

        return $this;
    }

    // ── Formatting Commands ────────────────────────────────────────────────────

    public function setBold(bool $on): self
    {
        $this->buffer .= self::ESC . 'E' . chr($on ? 1 : 0);

        return $this;
    }

    public function setUnderline(int $mode = 1): self
    {
        $this->buffer .= self::ESC . '-' . chr($mode);

        return $this;
    }

    public function setDoubleHeight(bool $on): self
    {
        $n = $on ? chr(0x11) : chr(0x00);
        $this->buffer .= self::ESC . '!' . $n;

        return $this;
    }

    public function setDoubleWidth(bool $on): self
    {
        $n = $on ? chr(0x20) : chr(0x00);
        $this->buffer .= self::ESC . '!' . $n;

        return $this;
    }

    public function setJustification(string $align): self
    {
        $n = match ($align) {
            'left' => 0,
            'center' => 1,
            'right' => 2,
            default => 0,
        };
        $this->buffer .= self::ESC . 'a' . chr($n);

        return $this;
    }

    // ── Buffer Access ──────────────────────────────────────────────────────────

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function reset(): self
    {
        $this->buffer = '';

        return $this;
    }

    // ── Internal ───────────────────────────────────────────────────────────────

    private function encodeText(string $text): string
    {
        if ($this->charSet === 'CP720') {
            $converted = @iconv('UTF-8', 'CP720//IGNORE', $text);

            return $converted !== false ? $converted : $text;
        }

        return $text;
    }
}
