// Tauri bridge — works whether or not @tauri-apps/api is installed.
// Detection uses two signals:
//   1. window.__TAURI_INTERNALS__ — injected by Tauri v2 runtime at startup
//   2. import.meta.env.TAURI_ARCH — set by Tauri build system (vite envPrefix includes 'TAURI_')
// Both are checked so the app works even if the runtime injection races the module evaluation.

type TauriInternals = {
  invoke: (command: string, args?: Record<string, unknown>) => Promise<unknown>
}

function getTauri(): TauriInternals | undefined {
  return (window as unknown as { __TAURI_INTERNALS__?: TauriInternals }).__TAURI_INTERNALS__
}

export const isTauriApp = (): boolean =>
  !!getTauri() || !!(import.meta.env.TAURI_ARCH)

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
