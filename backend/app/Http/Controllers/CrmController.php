<?php

namespace App\Http\Controllers;

use App\Models\CrmActivity;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class CrmController extends Controller
{
    /* ─── Web Views ──────────────────────────────────────────────────── */

    public function index(): \Illuminate\View\View
    {
        $stats = $this->buildStats();
        $segments = CustomerSegment::where('is_active', true)->orderBy('name')->get();

        return view('crm.index', compact('stats', 'segments'));
    }

    public function customer(int $id): \Illuminate\View\View
    {
        $customer = Customer::findOrFail($id);
        $activities = CrmActivity::where('customer_id', $id)
            ->orderByDesc('created_at')
            ->with('user')
            ->limit(50)
            ->get();

        $invoices = Invoice::where('customer_id', $id)
            ->orderByDesc('created_at')
            ->select('id', 'invoice_number', 'final_total', 'status', 'created_at')
            ->limit(20)
            ->get();

        return view('crm.customer', compact('customer', 'activities', 'invoices'));
    }

    /* ─── API ────────────────────────────────────────────────────────── */

    public function activities(int $customerId): JsonResponse
    {
        $activities = CrmActivity::where('customer_id', $customerId)
            ->with('user:id,full_name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'type_icon' => $a->type_icon,
                'subject' => $a->subject,
                'notes' => $a->notes,
                'outcome' => $a->outcome,
                'scheduled_at' => $a->scheduled_at?->format('Y-m-d H:i'),
                'completed_at' => $a->completed_at?->format('Y-m-d H:i'),
                'created_at' => $a->created_at->diffForHumans(),
                'user_name' => $a->user?->full_name,
            ]);

        return response()->json($activities);
    }

    public function storeActivity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'type' => 'required|in:call,email,visit,whatsapp,note,complaint,follow_up,sale,return',
            'subject' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:2000',
            'outcome' => 'nullable|in:positive,neutral,negative,pending',
            'scheduled_at' => 'nullable|date',
        ]);

        $activity = CrmActivity::create(array_merge($data, [
            'user_id' => Auth::id(),
            'outcome' => $data['outcome'] ?? 'neutral',
        ]));

        // Update customer lifecycle stage based on activity
        $this->updateLifecycle($data['customer_id']);

        return response()->json(['activity' => $activity->load('user')], 201);
    }

    public function updateActivity(Request $request, int $id): JsonResponse
    {
        $activity = CrmActivity::findOrFail($id);

        $data = $request->validate([
            'notes' => 'nullable|string|max:2000',
            'outcome' => 'required|in:positive,neutral,negative,pending',
            'completed_at' => 'nullable|date',
        ]);

        $activity->update($data);

        return response()->json(['activity' => $activity]);
    }

    public function deleteActivity(int $id): JsonResponse
    {
        CrmActivity::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Pending follow-ups (scheduled activities not yet completed).
     */
    public function followUps(Request $request): JsonResponse
    {
        $followUps = CrmActivity::pendingFollowUps()
            ->with('customer:id,name,phone')
            ->where('scheduled_at', '<=', now()->addDays(7))
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn ($a) => [
                'id'            => $a->id,
                'customer_name' => $a->customer?->name,
                'due_date'      => $a->scheduled_at?->toDateString(),
                'notes'         => $a->notes,
                'status'        => $a->status ?? 'pending',
                'activity_type' => $a->type,
            ]);

        return response()->json(['success' => true, 'data' => $followUps]);
    }

    /**
     * CRM Dashboard stats.
     */
    public function stats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->buildStats()]);
    }

    /**
     * All activities (not filtered by customer) for the CRM list view.
     */
    public function allActivities(Request $request): JsonResponse
    {
        $activities = CrmActivity::with('customer:id,name')
            ->latest()
            ->paginate(30);

        $data = $activities->map(fn ($a) => [
            'id'            => $a->id,
            'customer_name' => $a->customer?->name,
            'type'          => $a->type,
            'notes'         => $a->notes,
            'created_at'    => $a->created_at,
            'status'        => $a->status ?? 'done',
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Schedule a follow-up (stored as a pending CrmActivity).
     */
    public function storeFollowUp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id'   => 'required|integer|exists:customers,id',
            'due_date'      => 'required|date',
            'notes'         => 'nullable|string|max:5000',
            'activity_type' => 'nullable|in:call,email,meeting,note,task,other',
        ]);

        $activity = CrmActivity::create([
            'customer_id'  => $data['customer_id'],
            'type'         => $data['activity_type'] ?? 'call',
            'notes'        => $data['notes'] ?? null,
            'user_id'      => Auth::id(),
            'scheduled_at' => $data['due_date'],
            'status'       => 'pending',
        ]);

        return response()->json(['success' => true, 'data' => $activity], 201);
    }

    /* ─── Segments ───────────────────────────────────────────────────── */

    public function syncSegments(): JsonResponse
    {
        $this->syncAllSegments();

        return response()->json(['message' => 'Segments synced', 'segments' => CustomerSegment::all()]);
    }

    /* ─── Private Helpers ────────────────────────────────────────────── */

    private function buildStats(): array
    {
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('is_active', true)->count();
        $newThisMonth = Customer::whereMonth('created_at', now()->month)->count();
        $totalActivities = CrmActivity::count();
        $pendingFollowUps = CrmActivity::pendingFollowUps()
            ->where('scheduled_at', '<=', now()->addDays(7))
            ->count();

        $byLifecycle = Customer::select('lifecycle_stage', DB::raw('count(*) as cnt'))
            ->groupBy('lifecycle_stage')
            ->pluck('cnt', 'lifecycle_stage')
            ->toArray();

        $topCustomers = Customer::orderByDesc('lifetime_value')
            ->select('id', 'name', 'phone', 'lifetime_value', 'purchase_count', 'lifecycle_stage')
            ->limit(10)
            ->get();

        return [
            'total_customers'  => $totalCustomers,
            'active_customers' => $activeCustomers,
            'total_activities' => $totalActivities,
            'new_this_month'   => $newThisMonth,
            'pending_followups' => $pendingFollowUps,
            'by_lifecycle'     => $byLifecycle,
            'top_customers'    => $topCustomers,
        ];
    }

    private function updateLifecycle(int $customerId): void
    {
        try {
            $customer = Customer::find($customerId);
            if (! $customer) {
                return;
            }

            $purchaseCount = Invoice::where('customer_id', $customerId)
                ->where('status', 'paid')
                ->count();

            $ltv = Invoice::where('customer_id', $customerId)
                ->where('status', 'paid')
                ->sum('final_total');

            $lastPurchase = Invoice::where('customer_id', $customerId)
                ->where('status', 'paid')
                ->max('created_at');

            $daysSincePurchase = $lastPurchase ? now()->diffInDays($lastPurchase) : 999;

            $stage = match (true) {
                $purchaseCount === 0 => 'prospect',
                $daysSincePurchase > 90 => 'at_risk',
                $daysSincePurchase > 180 => 'churned',
                $purchaseCount >= 10 => 'loyal',
                $purchaseCount >= 3 => 'customer',
                default => 'customer',
            };

            $customer->update([
                'purchase_count' => $purchaseCount,
                'lifetime_value' => round($ltv, 2),
                'last_purchase_at' => $lastPurchase,
                'lifecycle_stage' => $stage,
            ]);
        } catch (Throwable) {
        }
    }

    private function syncAllSegments(): void
    {
        $segments = CustomerSegment::where('is_active', true)->get();
        foreach ($segments as $segment) {
            $count = $this->countSegment($segment);
            $segment->update(['customer_count' => $count, 'last_synced_at' => now()]);
        }
    }

    private function countSegment(CustomerSegment $segment): int
    {
        $rules = $segment->rules ?? [];
        $query = Customer::query();

        foreach ($rules as $rule) {
            match ($rule['field'] ?? '') {
                'lifecycle_stage' => $query->where('lifecycle_stage', $rule['value']),
                'min_ltv' => $query->where('lifetime_value', '>=', $rule['value']),
                'min_purchases' => $query->where('purchase_count', '>=', $rule['value']),
                default => null,
            };
        }

        return $query->count();
    }
}
