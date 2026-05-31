<?php

namespace App\Http\Controllers;

use App\Models\PaymentAccount;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class PaymentAccountController extends Controller
{
    use ApiResponse;

    public function index()
    {
        return $this->success([
            'accounts' => PaymentAccount::orderBy('sort_order')->get(),
        ]);
    }

    public function page()
    {
        return view('admin.payment-accounts');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'account_number' => 'nullable|string|max:100',
            'account_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $account = PaymentAccount::findOrFail($id);
        $account->update($data);

        return $this->success(['account' => $account]);
    }
}
