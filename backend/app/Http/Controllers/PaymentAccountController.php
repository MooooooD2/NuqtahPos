<?php

namespace App\Http\Controllers;

use App\Models\PaymentAccount;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class PaymentAccountController extends Controller
{
    use ApiResponse;

    public function publicIndex()
    {
        return response()->json([
            'methods' => PaymentAccount::configured(),
        ]);
    }

    public function index()
    {
        $accounts = PaymentAccount::orderBy('sort_order')->get();

        $accounts->each(function (PaymentAccount $acc) {
            if ($acc->method === 'whatsapp' && empty($acc->account_number)) {
                $acc->account_number = \Illuminate\Support\Facades\DB::connection('mysql')
                    ->table('settings')
                    ->where('key', 'saas_whatsapp_number')
                    ->value('value') ?: '';
            }
        });

        return $this->success([
            'accounts' => $accounts,
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
