// Tauri bridge — works whether or not @tauri-apps/api is installed.
// Detection uses two build-time signals plus the runtime injection:
//   1. window.__TAURI_INTERNALS__ — injected by Tauri v2 runtime at startup
//   2. import.meta.env.TAURI_ARCH — set manually or by Tauri v1
//   3. import.meta.env.TAURI_ENV_ARCH — set by Tauri v2 CLI during build
// Multiple signals checked so the app works regardless of Tauri CLI version.

type TauriInternals = {
  invoke: (command: string, args?: Record<string, unknown>) => Promise<unknown>
}

function getTauri(): TauriInternals | undefined {
  return (window as unknown as { __TAURI_INTERNALS__?: TauriInternals }).__TAURI_INTERNALS__
}

export const isTauriApp = (): boolean => {
  if (getTauri()) return true
  if (import.meta.env.TAURI_ARCH || import.meta.env.TAURI_ENV_ARCH) return true
  // Tauri v2 on Windows: http://tauri.localhost; on macOS/Linux: tauri://localhost
  const origin = window.location.origin
  return origin === 'http://tauri.localhost' || origin === 'https://tauri.localhost' || origin === 'tauri://localhost'
}

export async function invokeTauri<T = unknown>(
  command: string,
  args?: Record<string, unknown>,
): Promise<T | undefined> {
  try {
    return (await getTauri()?.invoke(command, args)) as T
  } catch {
    return undefined
  }
}
