import { useEffect, useRef, useState, useCallback } from 'react'
import { ScanLine, Camera, CameraOff, X } from 'lucide-react'

interface BarcodeScannerProps {
  onScan: (code: string) => void
  onClose?: () => void
  showCameraScanner?: boolean
}

// USB/keyboard wedge scanner — listens for rapid keystrokes ending in Enter
export function useKeyboardScanner(onScan: (code: string) => void, enabled = true) {
  const bufferRef = useRef<string>('')
  const lastKeyTime = useRef<number>(0)

  useEffect(() => {
    if (!enabled) return

    const handleKey = (e: KeyboardEvent) => {
      const now = Date.now()
      if (now - lastKeyTime.current > 100) bufferRef.current = ''
      lastKeyTime.current = now

      if (e.key === 'Enter') {
        const code = bufferRef.current.trim()
        if (code.length >= 4) onScan(code)
        bufferRef.current = ''
      } else if (e.key.length === 1) {
        bufferRef.current += e.key
      }
    }

    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [onScan, enabled])
}

// IScannerControls returned by ZXing's decodeFromVideoDevice
interface ScannerControls { stop: () => void }

// Camera-based scanner using ZXing (supports EAN-13, UPC-A, Code128, QR, DataMatrix, etc.)
export default function BarcodeScanner({ onScan, onClose, showCameraScanner = true }: BarcodeScannerProps) {
  const videoRef = useRef<HTMLVideoElement>(null)
  const controlsRef = useRef<ScannerControls | null>(null)
  const [active, setActive] = useState(false)
  const [error, setError] = useState('')

  const stopCamera = useCallback(() => {
    try { controlsRef.current?.stop() } catch { /* already stopped */ }
    controlsRef.current = null
    setActive(false)
  }, [])

  const startCamera = useCallback(async () => {
    setError('')
    try {
      const { BrowserMultiFormatReader } = await import('@zxing/browser')
      const reader = new BrowserMultiFormatReader()
      setActive(true)

      // decodeFromVideoDevice returns IScannerControls with a stop() method
      const controls = await reader.decodeFromVideoDevice(
        undefined,
        videoRef.current!,
        (result) => {
          if (result) {
            const code = result.getText()
            onScan(code)
            // Stop after first successful scan
            stopCamera()
          }
        },
      )
      controlsRef.current = controls as unknown as ScannerControls
    } catch (e) {
      const msg = e instanceof Error ? e.message : String(e)
      if (msg.includes('Permission') || msg.includes('NotAllowed') || msg.includes('denied')) {
        setError('Camera access denied — please allow camera access in your browser')
      } else if (msg.includes('Devices') || msg.includes('no camera') || msg.includes('device')) {
        setError('No camera found on this device')
      } else {
        setError(`Camera error: ${msg}`)
      }
      setActive(false)
    }
  }, [onScan, stopCamera])

  // Cleanup on unmount
  useEffect(() => () => stopCamera(), [stopCamera])

  if (!showCameraScanner) return null

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <span className="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2">
          <ScanLine className="h-4 w-4 text-primary-500" /> Camera Scanner
          {active && <span className="text-xs text-green-600 dark:text-green-400 animate-pulse">● Scanning…</span>}
        </span>
        <div className="flex gap-2">
          {!active ? (
            <button onClick={startCamera} className="btn btn-secondary text-xs py-1 px-3 flex items-center gap-1">
              <Camera className="h-3.5 w-3.5" /> Start Camera
            </button>
          ) : (
            <button onClick={stopCamera} className="btn btn-secondary text-xs py-1 px-3 flex items-center gap-1">
              <CameraOff className="h-3.5 w-3.5" /> Stop
            </button>
          )}
          {onClose && (
            <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600">
              <X className="h-4 w-4" />
            </button>
          )}
        </div>
      </div>

      {error && (
        <p className="text-xs text-red-500 bg-red-50 dark:bg-red-900/20 rounded px-2 py-1">{error}</p>
      )}

      {/* Video element always in DOM so videoRef works — visibility toggled by active */}
      <div
        className="relative rounded-xl overflow-hidden bg-black transition-all"
        style={{ maxHeight: active ? 280 : 0, aspectRatio: active ? '4/3' : undefined }}
      >
        <video ref={videoRef} className="w-full h-full object-cover" muted playsInline />

        {active && (
          <>
            {/* Corner markers */}
            <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
              <div className="relative w-56 h-36">
                <div className="absolute top-0 left-0 w-6 h-6 border-t-2 border-l-2 border-primary-400 rounded-tl" />
                <div className="absolute top-0 right-0 w-6 h-6 border-t-2 border-r-2 border-primary-400 rounded-tr" />
                <div className="absolute bottom-0 left-0 w-6 h-6 border-b-2 border-l-2 border-primary-400 rounded-bl" />
                <div className="absolute bottom-0 right-0 w-6 h-6 border-b-2 border-r-2 border-primary-400 rounded-br" />
                {/* Animated scan line */}
                <div className="absolute left-1 right-1 h-0.5 bg-primary-400 opacity-80" style={{ animation: 'scan-line 2s ease-in-out infinite', top: '50%' }} />
              </div>
            </div>
            <div className="absolute bottom-2 left-0 right-0 text-center">
              <span className="text-xs text-white/70 bg-black/40 rounded px-2 py-0.5">
                EAN-13 · UPC · QR · Code128 · DataMatrix
              </span>
            </div>
          </>
        )}
      </div>

      {!active && !error && (
        <p className="text-xs text-gray-400 text-center">
          Supports all barcode formats (EAN, UPC, QR, Code128…)
          <br />
          <span className="text-gray-300">USB/Bluetooth scanner: just scan — detected automatically</span>
        </p>
      )}
    </div>
  )
}
