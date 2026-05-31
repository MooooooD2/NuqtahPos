// ─── Auth ────────────────────────────────────────────────────────────────────

export interface User {
  id: number
  name: string
  email: string
  role: string
  permissions: string[]
  branch_id?: number
  branch?: Branch
  two_factor_enabled: boolean
  avatar?: string
  created_at: string
}

export interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
}

// ─── Branch / Tenant ─────────────────────────────────────────────────────────

export interface Branch {
  id: number
  name: string
  address?: string
  phone?: string
  is_active: boolean
  currency: string
  timezone: string
}

// ─── Products ─────────────────────────────────────────────────────────────────

export interface Product {
  id: number
  name: string
  sku: string
  barcode?: string
  category_id: number
  category?: Category
  brand_id?: number
  unit_id: number
  unit?: Unit
  price: number
  cost: number
  tax_rate: number
  tax_category_id?: number
  stock: number
  low_stock_threshold: number
  image?: string
  is_active: boolean
  has_variants: boolean
  variants?: ProductVariant[]
  description?: string
  created_at: string
}

export interface ProductVariant {
  id: number
  product_id: number
  name: string
  sku: string
  price: number
  stock: number
  attributes: Record<string, string>
}

export interface Category {
  id: number
  name: string
  parent_id?: number
  description?: string
}

export interface Unit {
  id: number
  name: string
  abbreviation: string
}

// ─── POS / Invoice ────────────────────────────────────────────────────────────

export interface CartItem {
  product_id: number
  product: Product
  quantity: number
  unit_price: number
  discount_amount: number
  discount_percent: number
  tax_amount: number
  subtotal: number
  notes?: string
}

export interface Invoice {
  id: number
  invoice_number: string
  type: 'sale' | 'return' | 'proforma' | 'quotation'
  status: 'draft' | 'paid' | 'partial' | 'cancelled' | 'refunded'
  customer_id?: number
  customer?: Customer
  items: InvoiceItem[]
  subtotal: number
  discount_amount: number
  tax_amount: number
  total: number
  paid_amount: number
  change_amount: number
  payment_method: PaymentMethod
  notes?: string
  cashier_id: number
  branch_id: number
  created_at: string
}

export interface InvoiceItem {
  id: number
  product_id: number
  product?: Product
  quantity: number
  unit_price: number
  discount_amount: number
  tax_amount: number
  subtotal: number
}

export type PaymentMethod = 'cash' | 'card' | 'wallet' | 'gift_card' | 'split' | 'credit'

// ─── Customer ─────────────────────────────────────────────────────────────────

export interface Customer {
  id: number
  name: string
  phone?: string
  email?: string
  address?: string
  customer_group_id?: number
  loyalty_points: number
  credit_limit: number
  outstanding_balance: number
  is_active: boolean
  created_at: string
}

// ─── Supplier ─────────────────────────────────────────────────────────────────

export interface Supplier {
  id: number
  name: string
  phone?: string
  email?: string
  address?: string
  tax_number?: string
  payment_terms: number
  outstanding_balance: number
  is_active: boolean
}

// ─── Stock ────────────────────────────────────────────────────────────────────

export interface StockMovement {
  id: number
  product_id: number
  product?: Product
  type: 'in' | 'out' | 'adjustment' | 'transfer'
  quantity: number
  reference_type?: string
  reference_id?: number
  notes?: string
  created_at: string
}

// ─── Purchase ─────────────────────────────────────────────────────────────────

export interface PurchaseOrder {
  id: number
  po_number: string
  supplier_id: number
  supplier?: Supplier
  status: 'draft' | 'sent' | 'received' | 'partial' | 'cancelled'
  items: PurchaseOrderItem[]
  subtotal: number
  tax_amount: number
  total: number
  expected_date?: string
  notes?: string
  created_at: string
}

export interface PurchaseOrderItem {
  id: number
  product_id: number
  product?: Product
  quantity: number
  received_quantity: number
  unit_cost: number
  total: number
}

// ─── Reports ──────────────────────────────────────────────────────────────────

export interface SalesReport {
  period: string
  total_sales: number
  total_invoices: number
  total_items_sold: number
  average_sale: number
  total_tax: number
  total_discount: number
  gross_profit: number
  profit_margin: number
}

export interface DashboardStats {
  today_sales: number
  today_invoices: number
  today_customers: number
  low_stock_count: number
  pending_orders: number
  top_products: Array<{ product: Product; quantity: number; revenue: number }>
  sales_trend: Array<{ date: string; amount: number }>
  payment_breakdown: Array<{ method: string; amount: number }>
}

// ─── Pagination ───────────────────────────────────────────────────────────────

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export interface ApiResponse<T> {
  success: boolean
  data: T
  message?: string
}

// ─── Offline ──────────────────────────────────────────────────────────────────

export interface SyncQueueItem {
  id: string
  type: 'invoice' | 'stock_adjustment' | 'customer'
  payload: unknown
  created_at: number
  retries: number
  status: 'pending' | 'syncing' | 'failed'
}

export type ThemeMode = 'light' | 'dark' | 'system'
