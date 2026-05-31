import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import { immer } from 'zustand/middleware/immer'
import type { User } from '@/types'

interface AuthStore {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  login: (user: User, token: string) => void
  logout: () => void
  updateUser: (user: Partial<User>) => void
}

export const useAuthStore = create<AuthStore>()(
  persist(
    immer((set) => ({
      user: null,
      token: null,
      isAuthenticated: false,

      login: (user, token) =>
        set((state) => {
          state.user = user
          state.token = token
          state.isAuthenticated = true
        }),

      logout: () =>
        set((state) => {
          state.user = null
          state.token = null
          state.isAuthenticated = false
        }),

      updateUser: (partial) =>
        set((state) => {
          if (state.user) {
            Object.assign(state.user, partial)
          }
        }),
    })),
    {
      name: 'pos-auth',
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({ token: state.token, user: state.user, isAuthenticated: state.isAuthenticated }),
    },
  ),
)
