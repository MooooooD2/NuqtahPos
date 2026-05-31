import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import type { ThemeMode } from '@/types'

interface UIStore {
  theme: ThemeMode
  sidebarCollapsed: boolean
  sidebarMobileOpen: boolean
  language: 'en' | 'ar'
  setTheme: (theme: ThemeMode) => void
  toggleSidebar: () => void
  setSidebarMobileOpen: (open: boolean) => void
  setLanguage: (lang: 'en' | 'ar') => void
}

export const useUIStore = create<UIStore>()(
  persist(
    (set) => ({
      theme: 'light',
      sidebarCollapsed: false,
      sidebarMobileOpen: false,
      language: 'en',

      setTheme: (theme) => {
        set({ theme })
        const root = document.documentElement
        if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          root.classList.add('dark')
        } else {
          root.classList.remove('dark')
        }
      },

      toggleSidebar: () => set((s) => ({ sidebarCollapsed: !s.sidebarCollapsed })),
      setSidebarMobileOpen: (open) => set({ sidebarMobileOpen: open }),
      setLanguage: (language) => {
        set({ language })
        document.documentElement.dir = language === 'ar' ? 'rtl' : 'ltr'
        document.documentElement.lang = language
      },
    }),
    { name: 'pos-ui' },
  ),
)
