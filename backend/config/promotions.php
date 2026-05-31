<?php

return [
    /*
     * Maximum percentage of the order total that stacked promotions may discount in total.
     * Prevents multiple simultaneously active promotions from granting a near-100% discount.
     * Overridable per-environment via PROMOTIONS_MAX_DISCOUNT_PERCENT.
     */
    'max_discount_percent' => env('PROMOTIONS_MAX_DISCOUNT_PERCENT', 50),
];
