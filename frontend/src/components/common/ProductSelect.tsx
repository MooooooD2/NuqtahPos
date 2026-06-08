import { useState, useRef, useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import { Search, X, ChevronDown } from 'lucide-react'
import { clsx } from 'clsx'
import { useTranslation } from 'react-i18next'

interface ApiProduct {
  id: number
  name: string
  barcode?: string | null
  category?: string | null
  quantity?: number
}

interface Props {
  value: string                                    // product id as string
  onChange: (id: string, name: string) => void
  className?: string
  placeholder?: string
  disabled?: boolean
}

export default function ProductSelect({
  value,
  onChange,
  className,
  placeholder,
  disabled = false,
}: Props) {
  const { t } = useTranslation('pos')
  const resolvedPlaceholder = placeholder ?? t('search_product')
  const [query, setQuery]             = useState('')
  const [selectedName, setSelectedName] = useState('')
  const [open, setOpen]               = useState(false)
  const containerRef                  = useRef<HTMLDivElement>(null)

  // Reset display when parent clears value
  useEffect(() => {
    if (!value) {
      setSelectedName('')
      setQuery('')
    }
  }, [value])

  const { data, isFetching } = useQuery({
    queryKey: ['product-select', query],
    queryFn: () =>
      apiGet<{ success: boolean; products: ApiProduct[] }>('/products', {
        search: query,
        per_page: 10,
      }),
    enabled: open,
    staleTime: 30_000,
  })

  const products = data?.products ?? []

  const handleSelect = (p: ApiProduct) => {
    onChange(String(p.id), p.name)
    setSelectedName(p.name)
    setQuery('')
    setOpen(false)
  }

  const handleClear = (e: React.MouseEvent) => {
    e.stopPropagation()
    onChange('', '')
    setSelectedName('')
    setQuery('')
  }

  // Close when focus leaves the entire container
  const handleBlur = () => {
    setTimeout(() => {
      if (!containerRef.current?.contains(document.activeElement)) {
        setOpen(false)
        setQuery('')
      }
    }, 150)
  }

  return (
    <div ref={containerRef} className={clsx('relative', className)}>
      {/* Input row */}
      <div className="relative">
        <Search className="absolute left-3 rtl:left-auto rtl:right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
        <input
          type="text"
          disabled={disabled}
          value={open ? query : selectedName}
          placeholder={selectedName || resolvedPlaceholder}
          onChange={(e) => { setQuery(e.target.value); setOpen(true) }}
          onFocus={() => { if (!disabled) setOpen(true) }}
          onBlur={handleBlur}
          className="input w-full pl-9 pr-8 rtl:pl-8 rtl:pr-9"
        />
        {value ? (
          <button
            type="button"
            onMouseDown={handleClear}
            className="absolute right-2 rtl:right-auto rtl:left-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        ) : (
          <ChevronDown className="absolute right-2 rtl:right-auto rtl:left-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-400 pointer-events-none" />
        )}
      </div>

      {/* Dropdown */}
      {open && !disabled && (
        <div className="absolute z-50 mt-1 w-full min-w-[220px] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto">
          {isFetching && (
            <p className="px-3 py-2 text-sm text-gray-400">{t('searching')}</p>
          )}
          {!isFetching && products.length === 0 && (
            <p className="px-3 py-2 text-sm text-gray-400">{t('no_products_found')}</p>
          )}
          {products.map((p) => (
            <button
              key={p.id}
              type="button"
              onMouseDown={() => handleSelect(p)}
              className="w-full text-left px-3 py-2 text-sm hover:bg-primary-50 dark:hover:bg-primary-900/20 flex items-center justify-between gap-3 border-b border-gray-50 dark:border-gray-700 last:border-0"
            >
              <span className="font-medium text-gray-900 dark:text-white truncate">{p.name}</span>
              <span className="text-xs text-gray-400 shrink-0 font-mono">
                {p.barcode ?? `#${p.id}`}
              </span>
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
