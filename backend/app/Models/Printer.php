<?php

namespace App\Models;

use App\Services\Printing\Connectors\ConnectorFactory;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Printer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name', 'branch_id', 'connection_type', 'ip_address',
        'port', 'usb_device', 'windows_printer_name',
        'paper_width', 'characters_per_line', 'character_set',
        'auto_cut', 'auto_open_drawer', 'copies',
        'is_active', 'is_default', 'capabilities', 'notes',
    ];

    protected $casts = [
        'auto_cut' => 'boolean',
        'auto_open_drawer' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'capabilities' => 'array',
        'port' => 'integer',
        'characters_per_line' => 'integer',
        'copies' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function printJobs()
    {
        return $this->hasMany(PrintJob::class);
    }

    public function printLogs()
    {
        return $this->hasMany(PrintLog::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId)
            ->orWhereNull('branch_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function getCharactersPerLine(): int
    {
        return $this->characters_per_line
            ?? ($this->paper_width === '58' ? 32 : 48);
    }

    public function supportsBarcode(): bool
    {
        return $this->capabilities['barcode'] ?? false;
    }

    public function supportsQrCode(): bool
    {
        return $this->capabilities['qr_code'] ?? true;
    }

    /**
     * Attempt a live connection test, sending ESC @ (initialize).
     */
    public function testConnection(): array
    {
        try {
            $connector = ConnectorFactory::make($this);
            $connector->open();
            $connector->send(chr(0x1B) . chr(0x40)); // ESC @ = Initialize
            $connector->close();

            return ['success' => true, 'message' => 'Connection OK'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── Model Events ───────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        // Auto-calculate characters_per_line from paper_width
        static::saving(function (self $printer) {
            if (empty($printer->characters_per_line)) {
                $printer->characters_per_line = $printer->paper_width === '58' ? 32 : 48;
            }
        });

        // Enforce single default per branch
        static::saving(function (self $printer) {
            if ($printer->is_default) {
                static::where('branch_id', $printer->branch_id)
                    ->where('id', '!=', $printer->id ?? 0)
                    ->update(['is_default' => false]);
            }
        });
    }
}
