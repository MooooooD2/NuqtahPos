<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Phase 10 — Multi-Currency Service
 * Fetches live exchange rates and converts amounts.
 */
class CurrencyService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get all active currencies (cached).
     */
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(
            'currencies.active',
            self::CACHE_TTL,
            fn () => Currency::active()->orderByDesc('is_base')->orderBy('code')->get(),
        );
    }

    /**
     * Convert an amount between two currencies.
     */
    public function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        $rates = $this->getRates();
        $fromRate = $rates[$from] ?? 1.0;
        $toRate = $rates[$to] ?? 1.0;

        // Convert: amount → base → target
        $inBase = $amount / $fromRate;

        return round($inBase * $toRate, 4);
    }

    /**
     * Update exchange rates from an external provider (fixer.io / openexchangerates).
     */
    public function updateRates(string $provider = 'fixer'): bool
    {
        $apiKey = config('services.exchange_rates.key');
        $baseCode = Currency::base()->code ?? 'EGP';

        try {
            $response = match ($provider) {
                'fixer' => Http::get('https://data.fixer.io/api/latest', [
                    'access_key' => $apiKey,
                    'base' => $baseCode,
                ]),
                'openexchangerates' => Http::get('https://openexchangerates.org/api/latest.json', [
                    'app_id' => $apiKey,
                    'base' => $baseCode,
                ]),
                default => throw new InvalidArgumentException("Unknown provider: {$provider}"),
            };

            if (! $response->successful()) {
                Log::warning('Exchange rate update failed', ['provider' => $provider, 'status' => $response->status()]);

                return false;
            }

            $rates = $response->json('rates', []);

            Currency::active()->each(function (Currency $currency) use ($rates, $provider) {
                $code = $currency->code;

                if (isset($rates[$code])) {
                    $currency->update([
                        'exchange_rate' => $rates[$code],
                        'rate_updated_at' => now(),
                    ]);

                    // Store history
                    DB::table('currency_rate_history')->insert([
                        'currency_code' => $code,
                        'rate' => $rates[$code],
                        'source' => $provider,
                        'recorded_at' => now(),
                    ]);
                }
            });

            Cache::forget('currencies.active');
            Cache::forget('currencies.rates');

            return true;
        } catch (Throwable $e) {
            Log::error('CurrencyService::updateRates', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Get code => rate map (cached).
     */
    public function getRates(): array
    {
        return Cache::remember(
            'currencies.rates',
            self::CACHE_TTL,
            fn () => Currency::active()->pluck('exchange_rate', 'code')->toArray(),
        );
    }

    /**
     * Format an amount with the given currency symbol.
     */
    public function format(float $amount, string $currencyCode): string
    {
        $currency = Currency::active()->firstWhere('code', $currencyCode);
        $symbol = $currency?->symbol ?? $currencyCode;

        return $symbol . ' ' . number_format($amount, 2);
    }
}
