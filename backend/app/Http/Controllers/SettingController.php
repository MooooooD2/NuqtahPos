<?php

namespace App\Http\Controllers;

use App\Services\SettingService;
use Exception;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private SettingService $settingService) {}

    public function index()
    {
        return view('settings.index');
    }

    public function all()
    {
        return response()->json(['settings' => $this->settingService->getAllGrouped()]);
    }

    public function update(Request $request)
    {
        $this->authorize('update_settings');

        $data = $request->validate([
            'settings' => 'required|array|max:100',
            'settings.*.key' => ['required', 'string', 'max:100', 'exists:settings,key'],
            // Values must not be nested arrays; strings and numerics are fine.
            // SettingService::updateBatch() additionally runs strip_tags() and an allowlist check.
            'settings.*.value' => ['nullable', 'max:2048'],
        ]);

        try {
            $this->settingService->updateBatch($data['settings']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => __('pos.settings_saved')]);
    }

    public function group(string $group)
    {
        $allowed = ['general', 'tax', 'pos', 'invoice', 'stock', 'accounting', 'printing', 'loyalty', 'inventory'];
        if (! in_array($group, $allowed, true)) {
            return response()->json([], 400);
        }

        return response()->json(['settings' => $this->settingService->getGroup($group)]);
    }
}
