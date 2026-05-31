<?php

namespace App\Services;

use App\Contracts\Repositories\SettingRepositoryInterface;
use Exception;

class SettingService
{
    private const ALLOWED_KEYS = [
        'store_name', 'store_address', 'store_phone', 'store_email', 'store_logo',
        'currency', 'currency_symbol', 'currency_ar', 'currency_en', 'currency_position',
        'default_language',
        'tax_enabled', 'tax_rate', 'tax_inclusive', 'tax_name_ar', 'tax_name_en', 'tax_number',
        'invoice_prefix', 'invoice_footer', 'invoice_header', 'invoice_notes',
        'show_tax_invoice', 'auto_print', 'receipt_copies',
        'default_payment', 'pos_sound', 'low_stock_alert', 'allow_negative_stock',
        'max_discount_percent', 'allow_cashier_price_change',
        'max_daily_withdrawal', 'min_cash_balance', 'cash_account_code',
        'revenue_account_code', 'profit_margin_target',
        'loyalty_enabled', 'loyalty_earn_rate', 'loyalty_redeem_value', 'loyalty_min_redeem',
        'inventory_valuation_method',
        'ip_whitelist', 'company_tax_number',
        // Thermal printing keys
        'print_on_sale', 'print_on_return', 'print_on_shift_close',
        'receipt_template', 'receipt_copies',
        'kitchen_printer_id', 'barcode_printer_id',
        'print_fallback_browser',
        'receipt_show_qr', 'receipt_show_barcode',
        'tax_registration_number',
    ];

    public function __construct(private SettingRepositoryInterface $settingRepo) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settingRepo->get($key, $default);
    }

    public function getGroup(string $group): array
    {
        return $this->settingRepo->getGroup($group);
    }

    public function getAllGrouped(): array
    {
        return $this->settingRepo->getAllGrouped();
    }

    public function updateBatch(array $settings): void
    {
        foreach ($settings as $item) {
            if (! in_array($item['key'], self::ALLOWED_KEYS, true)) {
                throw new Exception('مفتاح الإعداد غير مسموح به: ' . $item['key']);
            }
            $value = $item['value'] ?? '';
            if (is_string($value)) {
                $value = strip_tags($value);
            }
            $this->settingRepo->set($item['key'], $value);
            $this->settingRepo->forget($item['key']);
        }
    }

    public function getPosSettings(): array
    {
        $raw = $this->settingRepo->getGroup('pos')
             + $this->settingRepo->getGroup('tax')
             + $this->settingRepo->getGroup('general')
             + $this->settingRepo->getGroup('invoice');

        $settings = array_map(fn ($s) => match ($s['type'] ?? 'string') {
            'boolean' => (bool) $s['value'],
            'number' => (float) $s['value'],
            'json' => json_decode($s['value'], true),
            default => $s['value'],
        }, $raw);

        return $settings + [
            'currency_symbol' => 'ج.م',
            'store_name' => '',
            'store_address' => '',
            'store_phone' => '',
            'tax_enabled' => false,
            'tax_rate' => 0.0,
            'tax_name_ar' => 'ضريبة',
            'tax_name_en' => 'VAT',
            'tax_inclusive' => false,
            'invoice_footer' => '',
            'auto_print' => false,
            'pos_sound' => true,
            'default_payment' => 'cash',
        ];
    }
}
