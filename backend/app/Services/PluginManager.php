<?php

namespace App\Services;

use App\Models\Plugin;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PluginManager
{
    private string $pluginsPath;

    public function __construct()
    {
        $this->pluginsPath = base_path('plugins');
    }

    /**
     * Scan plugins/*\/plugin.json and return [slug => manifest array].
     */
    public function discover(): array
    {
        if (! File::isDirectory($this->pluginsPath)) {
            return [];
        }

        $manifests = [];
        foreach (File::directories($this->pluginsPath) as $dir) {
            $manifestPath = $dir . DIRECTORY_SEPARATOR . 'plugin.json';
            if (! File::exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode(File::get($manifestPath), true);
            if (! is_array($manifest) || empty($manifest['slug'])) {
                continue;
            }

            $manifest['path'] = $dir;
            $manifests[$manifest['slug']] = $manifest;
        }

        return $manifests;
    }

    /**
     * Register a PSR-4-ish autoloader for every discovered plugin's `namespace` => `src/`.
     */
    public function registerAutoloading(array $manifests): void
    {
        foreach ($manifests as $manifest) {
            if (empty($manifest['namespace'])) {
                continue;
            }
            $namespace = rtrim($manifest['namespace'], '\\') . '\\';
            $srcPath = $manifest['path'] . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;

            spl_autoload_register(function (string $class) use ($namespace, $srcPath) {
                if (! str_starts_with($class, $namespace)) {
                    return;
                }
                $relative = substr($class, strlen($namespace));
                $file = $srcPath . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
                if (File::exists($file)) {
                    require_once $file;
                }
            });
        }
    }

    /**
     * Sync the `plugins` table with what's on disk, then register the
     * ServiceProvider of every plugin that is enabled.
     */
    public function registerEnabled(): void
    {
        $manifests = $this->discover();
        $this->registerAutoloading($manifests);

        if (empty($manifests) || ! Schema::connection('mysql')->hasTable('plugins')) {
            return;
        }

        foreach ($manifests as $slug => $manifest) {
            $record = Plugin::firstOrCreate(
                ['slug' => $slug],
                ['name' => $manifest['name'] ?? $slug, 'version' => $manifest['version'] ?? '0.0.0', 'is_enabled' => true],
            );

            if ($record->is_enabled && ! empty($manifest['provider'])) {
                app()->register($manifest['provider']);
            }
        }
    }
}
