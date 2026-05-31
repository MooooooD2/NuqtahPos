<?php

namespace App\Services;

use App\Contracts\Repositories\SettingRepositoryInterface;
use App\Models\Product;

class TaxService
{
    public function __construct(
        private SettingRepositoryInterface $settingRepo,
    ) {}

    /**
     * Resolve the effective tax rate for a product.
     * Uses the product's tax category if assigned; falls back to the global tax_rate setting.
     */
    public function resolveRate(Product $product): float
    {
        if ($product->taxCategory && $product->taxCategory->is_active) {
            return (float) $product->taxCategory->rate;
        }

        $taxEnabled = (bool) $this->settingRepo->get('tax_enabled', false);

        return $taxEnabled ? (float) $this->settingRepo->get('tax_rate', 0) : 0.0;
    }

    /**
     * Calculate line-level tax for a given subtotal and rate.
     *
     * @return array{tax_rate: float, tax_amount: float, line_total: float}
     */
    public function calculateLineTax(float $subtotal, float $rate, bool $taxInclusive): array
    {
        if ($rate <= 0) {
            return ['tax_rate' => 0.0, 'tax_amount' => 0.0, 'line_total' => $subtotal];
        }

        if ($taxInclusive) {
            $taxAmount = $subtotal * $rate / (100 + $rate);

            return [
                'tax_rate' => $rate,
                'tax_amount' => round($taxAmount, 2),
                'line_total' => $subtotal,
            ];
        }

        $rawTax = $subtotal * ($rate / 100);

        return [
            'tax_rate' => $rate,
            'tax_amount' => round($rawTax, 2),
            'line_total' => round($subtotal + $rawTax, 2),
        ];
    }
}
