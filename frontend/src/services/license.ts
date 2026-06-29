import { api } from '@/services/api'
import { invokeTauri } from '@/lib/tauri'

interface LicenseResponse {
  success: boolean
  license: {
    id: number
    status: string
    expires_at: string | null
  }
  token: string
}

export async function getDeviceId(): Promise<string> {
  const id = await invokeTauri<string>('get_device_id')
  return id ?? 'unknown-device'
}

export async function activateLicense(licenseKey: string, deviceName?: string) {
  const deviceId = await getDeviceId()
  const { data } = await api.post<LicenseResponse>('/license/activate', {
    license_key: licenseKey,
    device_id: deviceId,
    device_name: deviceName,
  })
  return { ...data, deviceId }
}

export async function validateLicense(licenseKey: string, deviceId: string) {
  const { data } = await api.post<LicenseResponse>('/license/validate', {
    license_key: licenseKey,
    device_id: deviceId,
  })
  return data
}
