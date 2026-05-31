import { create } from 'zustand'
import { immer } from 'zustand/middleware/immer'
import type { CartItem, Customer, PaymentMethod, Product } from '@/types'

interface CartStore {
  items: CartItem[]
  customer: Customer | null
  discount_percent: number
  discount_amount: number
  payment_method: PaymentMethod
  notes: string
  held_orders: Array<{ id: string; items: CartItem[]; customer: Customer | null; timestamp: number }>

  // Computed
  subtotal: () => number
  tax_total: () => number
  total: () => number

  // Actions
  addItem: (product: Product, quantity?: number) => void
  removeItem: (product_id: number) => void
  updateQuantity: (product_id: number, qty: number) => void
  updateItemDiscount: (product_id: number, discount: number, type: 'amount' | 'percent') => void
  setCustomer: (customer: Customer | null) => void
  setDiscount: (amount: number, type: 'amount' | 'percent') => void
  setPaymentMethod: (method: PaymentMethod) => void
  setNotes: (notes: string) => void
  clearCart: () => void
  holdOrder: () => void
  resumeOrder: (id: string) => void
  deleteHeldOrder: (id: string) => void
}

const calcItemTax = (item: CartItem): number => {
  const taxable = (item.unit_price - item.discount_amount) * item.quantity
  return taxable * (item.product.tax_rate / 100)
}

const calcItemSubtotal = (item: CartItem): number => {
  return (item.unit_price - item.discount_amount) * item.quantity + calcItemTax(item)
}

export const useCartStore = create<CartStore>()(
  immer((set, get) => ({
    items: [],
    customer: null,
    discount_percent: 0,
    discount_amount: 0,
    payment_method: 'cash',
    notes: '',
    held_orders: [],

    subtotal: () => get().items.reduce((s, i) => s + (i.unit_price - i.discount_amount) * i.quantity, 0),
    tax_total: () => get().items.reduce((s, i) => s + calcItemTax(i), 0),
    total: () => {
      const { subtotal, tax_total, discount_amount, discount_percent } = get()
      const sub = subtotal()
      const orderDiscount = discount_amount > 0 ? discount_amount : sub * (discount_percent / 100)
      return sub - orderDiscount + tax_total()
    },

    addItem: (product, quantity = 1) =>
      set((state) => {
        const existing = state.items.find((i) => i.product_id === product.id)
        if (existing) {
          existing.quantity += quantity
          existing.subtotal = calcItemSubtotal(existing)
        } else {
          const newItem: CartItem = {
            product_id: product.id,
            product,
            quantity,
            unit_price: product.price,
            discount_amount: 0,
            discount_percent: 0,
            tax_amount: (product.price * quantity * product.tax_rate) / 100,
            subtotal: product.price * quantity * (1 + product.tax_rate / 100),
          }
          state.items.push(newItem)
        }
      }),

    removeItem: (product_id) =>
      set((state) => {
        state.items = state.items.filter((i) => i.product_id !== product_id)
      }),

    updateQuantity: (product_id, qty) =>
      set((state) => {
        const item = state.items.find((i) => i.product_id === product_id)
        if (item) {
          if (qty <= 0) {
            state.items = state.items.filter((i) => i.product_id !== product_id)
          } else {
            item.quantity = qty
            item.tax_amount = calcItemTax(item)
            item.subtotal = calcItemSubtotal(item)
          }
        }
      }),

    updateItemDiscount: (product_id, discount, type) =>
      set((state) => {
        const item = state.items.find((i) => i.product_id === product_id)
        if (item) {
          if (type === 'amount') {
            item.discount_amount = Math.min(discount, item.unit_price * item.quantity)
            item.discount_percent = 0
          } else {
            item.discount_percent = Math.min(discount, 100)
            item.discount_amount = (item.unit_price * item.quantity * discount) / 100
          }
          item.tax_amount = calcItemTax(item)
          item.subtotal = calcItemSubtotal(item)
        }
      }),

    setCustomer: (customer) =>
      set((state) => { state.customer = customer }),

    setDiscount: (amount, type) =>
      set((state) => {
        if (type === 'amount') {
          state.discount_amount = amount
          state.discount_percent = 0
        } else {
          state.discount_percent = amount
          state.discount_amount = 0
        }
      }),

    setPaymentMethod: (method) =>
      set((state) => { state.payment_method = method }),

    setNotes: (notes) =>
      set((state) => { state.notes = notes }),

    clearCart: () =>
      set((state) => {
        state.items = []
        state.customer = null
        state.discount_percent = 0
        state.discount_amount = 0
        state.payment_method = 'cash'
        state.notes = ''
      }),

    holdOrder: () =>
      set((state) => {
        if (state.items.length === 0) return
        state.held_orders.push({
          id: `hold-${Date.now()}`,
          items: [...state.items],
          customer: state.customer,
          timestamp: Date.now(),
        })
        state.items = []
        state.customer = null
        state.discount_percent = 0
        state.discount_amount = 0
      }),

    resumeOrder: (id) =>
      set((state) => {
        const order = state.held_orders.find((o) => o.id === id)
        if (order) {
          state.items = order.items
          state.customer = order.customer
          state.held_orders = state.held_orders.filter((o) => o.id !== id)
        }
      }),

    deleteHeldOrder: (id) =>
      set((state) => {
        state.held_orders = state.held_orders.filter((o) => o.id !== id)
      }),
  })),
)
