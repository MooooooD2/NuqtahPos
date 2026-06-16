import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';
// https://vite.dev/config/
export default defineConfig(({}) => {
    // Tauri v1 sets TAURI_ARCH; Tauri v2 sets TAURI_ENV_ARCH; manual builds may set either
    const isDesktop = process.env.TAURI_ARCH !== undefined
        || process.env.TAURI_ENV_ARCH !== undefined
        || process.env.TAURI_ENV_TARGET_TRIPLE !== undefined;
    return {
        plugins: [react()],
        base: isDesktop ? '/' : '/pos/',
        resolve: {
            alias: {
                '@': path.resolve(__dirname, './src'),
            },
        },
        // Prevent vite from obscuring Rust errors in Tauri
        clearScreen: false,
        server: {
            port: 5173,
            strictPort: true,
            host: isDesktop ? false : '0.0.0.0',
            proxy: {
                // Desktop (base='/'): browser sends /api/... directly
                // Web dev: api.ts uses http://localhost:8000 directly (absolute URL, avoids Vite /pos/ interception)
                '/api': { target: 'http://localhost:8000', changeOrigin: true },
                '/sanctum': { target: 'http://localhost:8000', changeOrigin: true },
                '/downloads': { target: 'http://localhost:8000', changeOrigin: true },
            },
        },
        envPrefix: ['VITE_', 'TAURI_'],
        build: {
            // Tauri uses Chromium on Windows and WebKit on macOS and Linux
            target: isDesktop ? ['es2021', 'chrome100', 'safari13'] : 'esnext',
            minify: (process.env.TAURI_DEBUG ? false : 'esbuild'),
            sourcemap: !!process.env.TAURI_DEBUG,
            outDir: 'dist',
            rollupOptions: {
                // Tauri APIs are injected by the Tauri runtime — never bundle them
                external: isDesktop ? [] : [
                    /^@tauri-apps\//,
                ],
            },
        },
    };
});
