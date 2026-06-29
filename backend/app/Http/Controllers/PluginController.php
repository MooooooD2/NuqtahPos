<?php

namespace App\Http\Controllers;

use App\Models\Plugin;
use App\Services\PluginManager;
use App\Traits\ApiResponse;

/**
 * @group Plugins
 */
class PluginController extends Controller
{
    use ApiResponse;

    public function __construct(private PluginManager $plugins)
    {
    }

    public function index()
    {
        $manifests = $this->plugins->discover();
        $records = Plugin::whereIn('slug', array_keys($manifests))->get()->keyBy('slug');

        $list = collect($manifests)->map(function ($manifest, $slug) use ($records) {
            $record = $records->get($slug);
            return [
                'slug' => $slug,
                'name' => $manifest['name'] ?? $slug,
                'version' => $manifest['version'] ?? '0.0.0',
                'description' => $manifest['description'] ?? '',
                'is_enabled' => $record?->is_enabled ?? true,
            ];
        })->values();

        return $this->success(['plugins' => $list]);
    }

    public function toggle(string $slug)
    {
        $plugin = Plugin::where('slug', $slug)->firstOrFail();
        $plugin->update(['is_enabled' => ! $plugin->is_enabled]);

        return $this->success(['plugin' => $plugin]);
    }
}
