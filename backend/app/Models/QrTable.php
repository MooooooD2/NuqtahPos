<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $branch_id
 * @property string $table_name
 * @property string $token
 * @property string|null $qr_code_path
 * @property bool $is_active
 * @property int $capacity
 */
class QrTable extends Model
{
    protected $fillable = [
        'branch_id', 'table_name', 'token', 'qr_code_path', 'is_active', 'capacity',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function generateToken(): string
    {
        do {
            $token = Str::random(32);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(QrOrder::class);
    }

    public function getMenuUrlAttribute(): string
    {
        return route('qr.menu', $this->token);
    }
}
