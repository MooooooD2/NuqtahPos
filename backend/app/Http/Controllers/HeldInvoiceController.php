<?php

namespace App\Http\Controllers;

use App\Http\Requests\HoldInvoiceRequest;
use App\Models\HeldInvoice;
use App\Services\HeldInvoiceService;
use App\Traits\ApiResponse;
use Exception;

class HeldInvoiceController extends Controller
{
    use ApiResponse;

    public function __construct(private HeldInvoiceService $heldService) {}

    public function active()
    {
        return $this->success(['held_invoices' => $this->heldService->active()]);
    }

    public function store(HoldInvoiceRequest $request)
    {
        try {
            $held = $this->heldService->hold($request->validated());

            return $this->success(['held_invoice' => $held], __('pos.invoice_held'), 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function resume(HeldInvoice $heldInvoice)
    {
        try {
            $held = $this->heldService->resume($heldInvoice);

            return $this->success(['held_invoice' => $held]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function discard(HeldInvoice $heldInvoice)
    {
        $this->authorize('discard', $heldInvoice);

        try {
            $this->heldService->discard($heldInvoice);

            return $this->success([], __('pos.invoice_discarded'));
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
