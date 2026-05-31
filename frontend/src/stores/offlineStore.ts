import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import { immer } from 'zustand/middleware/immer'
import { nanoid } from 'nanoid' // we'll polyfill this
import type { SyncQueueItem } from '@/types'

// Simple nanoid polyfill if not installed
const genId = () => Math.random().toString(36).slice(2, 10)

interface OfflineStore {
  isOnline: boolean
  syncQueue: SyncQueueItem[]
  lastSyncAt: number | null
  syncInProgress: boolean

  setOffline: (offline: boolean) => void
  enqueue: (type: SyncQueueItem['type'], payload: unknown) => void
  dequeue: (id: string) => void
  markFailed: (id: string) => void
  setSyncInProgress: (v: boolean) => void
  setLastSyncAt: (ts: number) => void
  clearQueue: () => void
}

export const useOfflineStore = create<OfflineStore>()(
  persist(
    immer((set) => ({
      isOnline: navigator.onLine,
      syncQueue: [],
      lastSyncAt: null,
      syncInProgress: false,

      setOffline: (offline) =>
        set((s) => { s.isOnline = !offline }),

      enqueue: (type, payload) =>
        set((s) => {
          s.syncQueue.push({
            id: genId(),
            type,
            payload,
            created_at: Date.now(),
            retries: 0,
            status: 'pending',
          })
        }),

      dequeue: (id) =>
        set((s) => {
          s.syncQueue = s.syncQueue.filter((i) => i.id !== id)
        }),

      markFailed: (id) =>
        set((s) => {
          const item = s.syncQueue.find((i) => i.id === id)
          if (item) {
            item.status = 'failed'
            item.retries += 1
          }
        }),

      setSyncInProgress: (v) =>
        set((s) => { s.syncInProgress = v }),

      setLastSyncAt: (ts) =>
        set((s) => { s.lastSyncAt = ts }),

      clearQueue: () =>
        set((s) => { s.syncQueue = [] }),
    })),
    {
      name: 'pos-offline-queue',
      storage: createJSONStorage(() => localStorage),
    },
  ),
)

// Setup online/offline listeners
if (typeof window !== 'undefined') {
  window.addEventListener('online', () => useOfflineStore.getState().setOffline(false))
  window.addEventListener('offline', () => useOfflineStore.getState().setOffline(true))
}
