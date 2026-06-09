import { useRef } from 'react'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import { Printer, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'

export interface InvoiceItem {
  product_name: string
  quantity: number
  price: string | number
  subtotal: string | number
  unit_abbreviation?: string | null
  tax_amount?: number
}

export interface PrintableInvoice {
  id?: number
  invoice_number: string
  customer_name?: string | null
  customer_phone?: string | null
  cashier_name?: string | null
  payment_method?: string | null
  cash_received?: string | number | null
  change_amount?: string | number | null
  subtotal?: string | number | null
  discount?: string | number | null
  tax_amount?: string | number | null
  tax_rate?: string | number | null
  final_total: string | number
  created_at?: string | null
  status?: string | null
  notes?: string | null
  items?: InvoiceItem[]
}

interface Props {
  invoice: PrintableInvoice | null
  onClose: () => void
  title?: string
}

function fmt(val?: string | number | null, digits = 2): string {
  return parseFloat(String(val ?? 0)).toFixed(digits)
}

export default function InvoicePrintModal({ invoice, onClose, title }: Props) {
  const printRef = useRef<HTMLDivElement>(null)
  const { t } = useTranslation('pos')

  const getPayLabel = (method?: string | null): string => {
    const map: Record<string, string> = {
      cash: t('cash'), card: t('card_pos'), wallet: t('wallet'),
      credit: t('credit'), bank: t('bank_transfer'),
    }
    return method ? (map[method] ?? method) : '—'
  }

  const { data: settingsData } = useQuery({
    queryKey: ['settings-store'],
    queryFn: () => apiGet<Record<string, string>>('/settings/group/store'),
    staleTime: 300_000,
    retry: false,
  })

  if (!invoice) return null

  const storeName = settingsData?.store_name ?? settingsData?.business_name ?? 'POS Store'
  const storePhone = settingsData?.store_phone ?? settingsData?.phone ?? ''
  const storeAddress = settingsData?.store_address ?? settingsData?.address ?? ''
  const storeFooter = settingsData?.receipt_footer ?? settingsData?.footer_text ?? 'Thank you for your purchase!'
  const taxLabel = settingsData?.tax_label ?? 'Tax'

  const subtotal = parseFloat(fmt(invoice.subtotal ?? invoice.final_total))
  const discount = parseFloat(fmt(invoice.discount ?? 0))
  const tax = parseFloat(fmt(invoice.tax_amount ?? 0))
  const total = parseFloat(fmt(invoice.final_total))
  const cashReceived = parseFloat(fmt(invoice.cash_received ?? 0))
  const change = parseFloat(fmt(invoice.change_amount ?? 0))
  const hasChange = cashReceived > 0 && change >= 0

  const handlePrint = () => {
    const printContent = printRef.current
    if (!printContent) return

    const printWindow = window.open('', '_blank', 'width=400,height=700')
    if (!printWindow) { window.print(); return }

    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <meta charset="utf-8" />
          <title>Invoice ${invoice.invoice_number}</title>
          <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; background: #fff; padding: 8px; }
            .receipt { max-width: 300px; margin: 0 auto; }
            .center { text-align: center; }
            .right { text-align: right; }
            .bold { font-weight: bold; }
            .store-name { font-size: 18px; font-weight: bold; margin-bottom: 2px; }
            .divider { border-top: 1px dashed #000; margin: 6px 0; }
            .divider-solid { border-top: 1px solid #000; margin: 6px 0; }
            .row { display: flex; justify-content: space-between; margin: 2px 0; }
            .row .label { color: #444; }
            .items-table { width: 100%; border-collapse: collapse; margin: 4px 0; }
            .items-table th { font-weight: bold; font-size: 11px; text-align: left; padding: 2px 0; border-bottom: 1px solid #000; }
            .items-table th.right, .items-table td.right { text-align: right; }
            .items-table td { padding: 2px 0; font-size: 11px; vertical-align: top; }
            .total-row { font-size: 14px; font-weight: bold; }
            .footer { margin-top: 8px; text-align: center; font-size: 11px; color: #555; }
            @media print { body { padding: 0; } }
          </style>
        </head>
        <body>${printContent.innerHTML}</body>
      </html>
    `)
    printWindow.document.close()
    printWindow.focus()
    setTimeout(() => { printWindow.print(); printWindow.close() }, 300)
  }

  const invoiceDate = invoice.created_at
    ? new Date(invoice.created_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })
    : new Date().toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />

      <div className="relative z-10 flex flex-col max-h-[90vh] w-full max-w-sm bg-white dark:bg-gray-800 rounded-2xl shadow-2xl">
        {/* Modal header */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
          <h2 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <Printer className="h-4 w-4 text-primary-500" />
            {title ?? `${t('invoice_hash')}${invoice.invoice_number}`}
          </h2>
          <button onClick={onClose} className="p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Receipt preview */}
        <div className="overflow-y-auto flex-1 p-4">
          <div
            ref={printRef}
            className="receipt bg-white text-black font-mono text-xs mx-auto"
            style={{ maxWidth: 300 }}
          >
            {/* Store header */}
            <div className="center mb-1">
              <div className="store-name bold" style={{ fontSize: 16, fontWeight: 'bold', marginBottom: 2 }}>{storeName}</div>
              {storeAddress && <div style={{ fontSize: 11, color: '#555' }}>{storeAddress}</div>}
              {storePhone && <div style={{ fontSize: 11, color: '#555' }}>{storePhone}</div>}
            </div>

            <div className="divider" style={{ borderTop: '1px dashed #000', margin: '6px 0' }} />

            {/* Invoice metadata */}
            <div>
              <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '2px 0' }}>
                <span style={{ color: '#555' }}>{t('invoice_hash')}</span>
                <span className="bold">{invoice.invoice_number}</span>
              </div>
              <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '2px 0' }}>
                <span style={{ color: '#555' }}>{t('date')}</span>
                <span>{invoiceDate}</span>
              </div>
              {invoice.cashier_name && (
                <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '2px 0' }}>
                  <span style={{ color: '#555' }}>{t('cashier')}</span>
                  <span>{invoice.cashier_name}</span>
                </div>
              )}
              {invoice.customer_name && (
                <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '2px 0' }}>
                  <span style={{ color: '#555' }}>{t('customer')}</span>
                  <span>{invoice.customer_name}</span>
                </div>
              )}
            </div>

            <div className="divider" style={{ borderTop: '1px dashed #000', margin: '6px 0' }} />

            {/* Items */}
            {invoice.items && invoice.items.length > 0 ? (
              <table style={{ width: '100%', borderCollapse: 'collapse', marginBottom: 4 }}>
                <thead>
                  <tr style={{ borderBottom: '1px solid #000' }}>
                    <th style={{ textAlign: 'left', padding: '2px 0', fontSize: 11 }}>{t('item_col')}</th>
                    <th style={{ textAlign: 'center', padding: '2px 0', fontSize: 11, width: 30 }}>{t('qty')}</th>
                    <th style={{ textAlign: 'right', padding: '2px 0', fontSize: 11, width: 50 }}>{t('price')}</th>
                    <th style={{ textAlign: 'right', padding: '2px 0', fontSize: 11, width: 55 }}>{t('total')}</th>
                  </tr>
                </thead>
                <tbody>
                  {invoice.items.map((item, i) => (
                    <tr key={i}>
                      <td style={{ padding: '2px 0', fontSize: 11, paddingRight: 4 }}>
                        {item.product_name}
                        {item.unit_abbreviation && <span style={{ color: '#888', fontSize: 10 }}> {item.unit_abbreviation}</span>}
                      </td>
                      <td style={{ textAlign: 'center', padding: '2px 0', fontSize: 11 }}>{item.quantity}</td>
                      <td style={{ textAlign: 'right', padding: '2px 0', fontSize: 11 }}>{fmt(item.price)}</td>
                      <td style={{ textAlign: 'right', padding: '2px 0', fontSize: 11, fontWeight: 'bold' }}>{fmt(item.subtotal)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <div style={{ textAlign: 'center', color: '#888', fontSize: 11, margin: '4px 0' }}>{t('no_items')}</div>
            )}

            <div style={{ borderTop: '1px solid #000', margin: '6px 0' }} />

            {/* Totals */}
            <div>
              <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '2px 0' }}>
                <span style={{ color: '#555' }}>{t('subtotal')}</span>
                <span>{fmt(subtotal)}</span>
              </div>
              {discount > 0 && (
                <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '2px 0' }}>
                  <span style={{ color: '#555' }}>{t('discount')}</span>
                  <span style={{ color: '#c00' }}>-{fmt(discount)}</span>
                </div>
              )}
              {tax > 0 && (
                <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '2px 0' }}>
                  <span style={{ color: '#555' }}>{taxLabel}</span>
                  <span>{fmt(tax)}</span>
                </div>
              )}
              <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '4px 0', fontWeight: 'bold', fontSize: 14 }}>
                <span>{t('total').toUpperCase()}</span>
                <span>{fmt(total)}</span>
              </div>
            </div>

            <div style={{ borderTop: '1px dashed #000', margin: '6px 0' }} />

            {/* Payment */}
            <div>
              <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '2px 0' }}>
                <span style={{ color: '#555' }}>{t('payment_title')}</span>
                <span style={{ textTransform: 'capitalize' }}>{getPayLabel(invoice.payment_method)}</span>
              </div>
              {hasChange && (
                <>
                  <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '2px 0' }}>
                    <span style={{ color: '#555' }}>{t('cash_received')}</span>
                    <span>{fmt(cashReceived)}</span>
                  </div>
                  <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '2px 0' }}>
                    <span style={{ color: '#555' }}>{t('change_label')}</span>
                    <span>{fmt(change)}</span>
                  </div>
                </>
              )}
            </div>

            {invoice.notes && (
              <>
                <div style={{ borderTop: '1px dashed #000', margin: '6px 0' }} />
                <div style={{ fontSize: 11, color: '#555', textAlign: 'center' }}>{invoice.notes}</div>
              </>
            )}

            <div style={{ borderTop: '1px dashed #000', margin: '8px 0 4px' }} />
            <div style={{ textAlign: 'center', fontSize: 11, color: '#555' }}>{storeFooter}</div>
          </div>
        </div>

        {/* Actions */}
        <div className="flex gap-2 px-4 py-3 border-t border-gray-100 dark:border-gray-700">
          <button onClick={onClose} className="btn btn-secondary flex-1">{t('close')}</button>
          <button
            onClick={handlePrint}
            className="btn btn-primary flex-1 flex items-center justify-center gap-2"
          >
            <Printer className="h-4 w-4" /> {t('print')}
          </button>
        </div>
      </div>
    </div>
  )
}
