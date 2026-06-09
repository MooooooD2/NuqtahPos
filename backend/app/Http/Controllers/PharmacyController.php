<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Services\SequenceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PharmacyController extends Controller
{
    use ApiResponse;

    /* ─── Dashboard ─────────────────────────────────────────────────────── */

    public function dashboard(): JsonResponse
    {
        $totalMedicines  = Medicine::where('is_active', true)->count();
        $expiringSoon    = MedicineBatch::where('quantity', '>', 0)
            ->whereBetween('expiry_date', [today(), today()->addDays(30)])
            ->distinct('medicine_id')->count('medicine_id');
        $expiredBatches  = MedicineBatch::where('quantity', '>', 0)
            ->where('expiry_date', '<', today())->count();
        $lowStock        = Medicine::where('is_active', true)->get()
            ->filter(fn ($m) => $m->total_stock <= $m->reorder_level && $m->total_stock > 0)->count();
        $outOfStock      = Medicine::where('is_active', true)->get()
            ->filter(fn ($m) => $m->total_stock === 0)->count();
        $pendingRx       = Prescription::where('status', 'pending')->count();
        $totalRxToday    = Prescription::whereDate('created_at', today())->count();

        $expiringList = MedicineBatch::with('medicine:id,name_ar,name_en')
            ->where('quantity', '>', 0)
            ->where('expiry_date', '<=', today()->addDays(90))
            ->orderBy('expiry_date')
            ->limit(10)
            ->get()
            ->map(fn ($b) => [
                'id'             => $b->id,
                'medicine_name'  => $b->medicine?->name_ar,
                'lot_number'     => $b->lot_number,
                'quantity'       => $b->quantity,
                'expiry_date'    => $b->expiry_date?->toDateString(),
                'expiry_status'  => $b->expiry_status,
                'days_to_expiry' => $b->days_to_expiry,
            ]);

        return $this->success(compact(
            'totalMedicines', 'expiringSoon', 'expiredBatches',
            'lowStock', 'outOfStock', 'pendingRx', 'totalRxToday', 'expiringList'
        ));
    }

    /* ─── Medicines ─────────────────────────────────────────────────────── */

    public function medicines(Request $request): JsonResponse
    {
        $search   = $request->query('search', '');
        $category = $request->query('category', '');
        $perPage  = min((int) $request->query('per_page', 30), 100);

        $query = Medicine::query()
            ->when($search, fn ($q) => $q->where('name_ar', 'like', "%{$search}%")
                ->orWhere('name_en', 'like', "%{$search}%")
                ->orWhere('generic_name', 'like', "%{$search}%")
                ->orWhere('barcode', $search))
            ->when($category, fn ($q) => $q->where('category', $category));

        $items = $query->latest()->paginate($perPage)->items();

        $data = collect($items)->map(fn ($m) => array_merge($m->toArray(), [
            'total_stock'   => $m->total_stock,
            'nearest_expiry' => $m->nearest_expiry,
        ]));

        return $this->success(['data' => $data, 'total' => $query->count()]);
    }

    public function storeMedicine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_ar'               => 'required|string|max:255',
            'name_en'               => 'nullable|string|max:255',
            'generic_name'          => 'nullable|string|max:255',
            'category'              => 'nullable|in:antibiotic,analgesic,antihypertensive,diabetes,vitamin,other',
            'dosage_form'           => 'nullable|in:tablet,capsule,syrup,injection,cream,drops,other',
            'strength'              => 'nullable|string|max:100',
            'unit'                  => 'nullable|string|max:50',
            'barcode'               => 'nullable|string|unique:medicines,barcode',
            'manufacturer'          => 'nullable|string|max:255',
            'requires_prescription' => 'boolean',
            'controlled_drug'       => 'boolean',
            'reorder_level'         => 'nullable|integer|min:0',
            'selling_price'         => 'nullable|numeric|min:0',
            'cost_price'            => 'nullable|numeric|min:0',
            'notes'                 => 'nullable|string|max:1000',
            'is_active'             => 'boolean',
        ]);

        $medicine = Medicine::create($data);

        return $this->success(['medicine' => $medicine], '', 201);
    }

    public function updateMedicine(Request $request, int $id): JsonResponse
    {
        $medicine = Medicine::findOrFail($id);

        $data = $request->validate([
            'name_ar'               => 'sometimes|string|max:255',
            'name_en'               => 'nullable|string|max:255',
            'generic_name'          => 'nullable|string|max:255',
            'category'              => 'nullable|in:antibiotic,analgesic,antihypertensive,diabetes,vitamin,other',
            'dosage_form'           => 'nullable|in:tablet,capsule,syrup,injection,cream,drops,other',
            'strength'              => 'nullable|string|max:100',
            'unit'                  => 'nullable|string|max:50',
            'barcode'               => 'nullable|string|unique:medicines,barcode,' . $id,
            'manufacturer'          => 'nullable|string|max:255',
            'requires_prescription' => 'boolean',
            'controlled_drug'       => 'boolean',
            'reorder_level'         => 'nullable|integer|min:0',
            'selling_price'         => 'nullable|numeric|min:0',
            'cost_price'            => 'nullable|numeric|min:0',
            'notes'                 => 'nullable|string|max:1000',
            'is_active'             => 'boolean',
        ]);

        $medicine->update($data);

        return $this->success(['medicine' => $medicine->fresh()]);
    }

    public function deleteMedicine(int $id): JsonResponse
    {
        $medicine = Medicine::findOrFail($id);

        if ($medicine->prescriptionItems()->exists()) {
            return $this->error('لا يمكن حذف دواء مرتبط بوصفة طبية', 422);
        }

        $medicine->batches()->delete();
        $medicine->delete();

        return $this->success([], 'تم الحذف');
    }

    /* ─── Batches ────────────────────────────────────────────────────────── */

    public function batches(Request $request): JsonResponse
    {
        $medicineId = $request->query('medicine_id');
        $status     = $request->query('status', '');   // expired|critical|warning|ok
        $perPage    = min((int) $request->query('per_page', 30), 100);

        $query = MedicineBatch::with('medicine:id,name_ar,name_en', 'supplier:id,name')
            ->when($medicineId, fn ($q) => $q->where('medicine_id', $medicineId))
            ->orderBy('expiry_date');

        $items = $query->paginate($perPage)->items();

        $data = collect($items)
            ->when($status, fn ($col) => $col->filter(fn ($b) => $b->expiry_status === $status))
            ->map(fn ($b) => array_merge($b->toArray(), [
                'medicine_name'  => $b->medicine?->name_ar,
                'supplier_name'  => $b->supplier?->name,
            ]));

        return $this->success(['data' => $data->values()]);
    }

    public function storeBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'medicine_id'    => 'required|exists:medicines,id',
            'lot_number'     => 'nullable|string|max:100',
            'quantity'       => 'required|integer|min:1',
            'expiry_date'    => 'required|date|after:today',
            'purchase_price' => 'nullable|numeric|min:0',
            'supplier_id'    => 'nullable|integer',
            'received_at'    => 'nullable|date',
            'notes'          => 'nullable|string|max:500',
        ]);

        $batch = MedicineBatch::create($data);

        return $this->success(['batch' => array_merge($batch->toArray(), [
            'expiry_status'  => $batch->expiry_status,
            'days_to_expiry' => $batch->days_to_expiry,
        ])], '', 201);
    }

    public function deleteBatch(int $id): JsonResponse
    {
        MedicineBatch::findOrFail($id)->delete();
        return $this->success([], 'تم الحذف');
    }

    /* ─── Prescriptions ─────────────────────────────────────────────────── */

    public function prescriptions(Request $request): JsonResponse
    {
        $status  = $request->query('status', '');
        $search  = $request->query('search', '');
        $perPage = min((int) $request->query('per_page', 20), 100);

        $items = Prescription::with(['items.medicine:id,name_ar'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where('patient_name', 'like', "%{$search}%")
                ->orWhere('prescription_number', 'like', "%{$search}%")
                ->orWhere('doctor_name', 'like', "%{$search}%"))
            ->latest()
            ->paginate($perPage)
            ->items();

        $data = collect($items)->map(fn ($rx) => array_merge($rx->toArray(), [
            'items_count'     => $rx->items->count(),
            'items'           => $rx->items->map(fn ($i) => [
                'id'                  => $i->id,
                'medicine_id'         => $i->medicine_id,
                'medicine_name'       => $i->medicine?->name_ar,
                'quantity_prescribed' => $i->quantity_prescribed,
                'quantity_dispensed'  => $i->quantity_dispensed,
                'dosage_instructions' => $i->dosage_instructions,
                'status'              => $i->status,
            ]),
        ]));

        return $this->success(['data' => $data]);
    }

    public function storePrescription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_name'   => 'required|string|max:255',
            'patient_phone'  => 'nullable|string|max:30',
            'doctor_name'    => 'required|string|max:255',
            'doctor_phone'   => 'nullable|string|max:30',
            'clinic_name'    => 'nullable|string|max:255',
            'issued_date'    => 'required|date',
            'expiry_date'    => 'nullable|date|after:issued_date',
            'notes'          => 'nullable|string|max:1000',
            'items'          => 'required|array|min:1',
            'items.*.medicine_id'        => 'required|exists:medicines,id',
            'items.*.quantity_prescribed' => 'required|integer|min:1',
            'items.*.dosage_instructions' => 'nullable|string|max:500',
        ]);

        $rx = DB::transaction(function () use ($data) {
            $prescription = Prescription::create([
                'prescription_number' => SequenceService::next('rx', 'RX'),
                'patient_name'   => $data['patient_name'],
                'patient_phone'  => $data['patient_phone'] ?? null,
                'doctor_name'    => $data['doctor_name'],
                'doctor_phone'   => $data['doctor_phone'] ?? null,
                'clinic_name'    => $data['clinic_name'] ?? null,
                'issued_date'    => $data['issued_date'],
                'expiry_date'    => $data['expiry_date'] ?? null,
                'notes'          => $data['notes'] ?? null,
                'status'         => 'pending',
            ]);

            foreach ($data['items'] as $item) {
                PrescriptionItem::create([
                    'prescription_id'     => $prescription->id,
                    'medicine_id'         => $item['medicine_id'],
                    'quantity_prescribed' => $item['quantity_prescribed'],
                    'dosage_instructions' => $item['dosage_instructions'] ?? null,
                    'quantity_dispensed'  => 0,
                    'status'              => 'pending',
                ]);
            }

            return $prescription->load('items.medicine:id,name_ar');
        });

        return $this->success(['prescription' => $rx], '', 201);
    }

    public function dispensePrescription(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'items'                       => 'required|array|min:1',
            'items.*.id'                  => 'required|exists:prescription_items,id',
            'items.*.quantity_dispensed'  => 'required|integer|min:0',
        ]);

        $prescription = Prescription::with('items')->findOrFail($id);

        DB::transaction(function () use ($request, $prescription) {
            foreach ($request->items as $row) {
                $item = $prescription->items->firstWhere('id', $row['id']);
                if (! $item) continue;

                $dispensed = min((int) $row['quantity_dispensed'], $item->quantity_prescribed);
                $item->update([
                    'quantity_dispensed' => $dispensed,
                    'status'             => $dispensed >= $item->quantity_prescribed ? 'dispensed' : 'pending',
                ]);
            }

            $prescription->refresh();
            $allDone  = $prescription->items->every(fn ($i) => $i->status === 'dispensed');
            $anyDone  = $prescription->items->some(fn ($i) => $i->quantity_dispensed > 0);

            $prescription->update([
                'status'       => $allDone ? 'fully_dispensed' : ($anyDone ? 'partially_dispensed' : 'pending'),
                'dispensed_by' => Auth::id(),
            ]);
        });

        return $this->success(['prescription' => $prescription->fresh()->load('items.medicine:id,name_ar')]);
    }
}
