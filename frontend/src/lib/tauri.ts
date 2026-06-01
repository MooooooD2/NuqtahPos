// Tauri bridge — works whether or not @tauri-apps/api is installed.
// In the desktop app, window.__TAURI_INTERNALS__ is injected by the runtime.
// In the web browser it's undefined, so every call is a safe no-op.

type TauriInternals = {
  invoke: (command: string, args?: Record<string, unknown>) => Promise<unknown>
}

function getTauri(): TauriInternals | undefined {
  return (window as unknown as { __TAURI_INTERNALS__?: TauriInternals }).__TAURI_INTERNALS__
}

export const isTauriApp = (): boolean => !!getTauri()

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
