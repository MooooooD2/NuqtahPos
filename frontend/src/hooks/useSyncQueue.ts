import { useEffect } from 'react'
import { useOfflineStore } from '@/stores/offlineStore'
import { apiPost } from '@/services/api'
import toast from 'react-hot-toast'

const ENDPOINT: Record<string, string> = {
  invoice: '/invoices',
  stock_adjustment: '/inventory/adjustments',
  customer: '/customers/quick',
}

export function useSyncQueue() {
  const isOnline = useOfflineStore((s) => s.isOnline)

  useEffect(() => {
    if (!isOnline) return
    const store = useOfflineStore.getState()
    if (store.syncInProgress) return

    const pending = store.syncQueue.filter((i) => i.status === 'pending' && i.retries < 3)
    if (pending.length === 0) return

    store.setSyncInProgress(true)

    const run = async () => {
      let synced = 0
      let failed = 0
      for (const item of pending) {
        const endpoint = ENDPOINT[item.type]
        if (!endpoint) { useOfflineStore.getState().dequeue(item.id); continue }
        try {
          await apiPost(endpoint, item.payload as object)
          useOfflineStore.getState().dequeue(item.id)
          synced++
        } catch {
          useOfflineStore.getState().markFailed(item.id)
          failed++
        }
      }
      useOfflineStore.getState().setLastSyncAt(Date.now())
      useOfflineStore.getState().setSyncInProgress(false)
      if (synced > 0) toast.success(`Synced ${synced} offline ${synced === 1 ? 'item' : 'items'}`)
      if (failed > 0) toast.error(`${failed} item(s) failed to sync — will retry`)
    }

    run()
  }, [isOnline])
}
