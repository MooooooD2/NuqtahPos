import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'

interface LicenseStore {
  licenseKey: string | null
  deviceId: string | null
  token: string | null
  expiresAt: string | null
  activate: (licenseKey: string, deviceId: string, token: string, expiresAt: string | null) => void
  clear: () => void
}

export const useLicenseStore = create<LicenseStore>()(
  persist(
    (set) => ({
      licenseKey: null,
      deviceId: null,
      token: null,
      expiresAt: null,

      activate: (licenseKey, deviceId, token, expiresAt) =>
        set({ licenseKey, deviceId, token, expiresAt }),

      clear: () => set({ licenseKey: null, deviceId: null, token: null, expiresAt: null }),
    }),
    {
      name: 'pos-license',
      storage: createJSONStorage(() => localStorage),
    },
  ),
)
