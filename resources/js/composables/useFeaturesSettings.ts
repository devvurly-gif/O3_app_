import { ref, computed, onMounted } from 'vue'

interface PackageInfo {
  current_package: string
  features: string[]
  available_packages: Record<string, any>
  is_ocr_import_enabled: boolean
}

const packageInfo = ref<PackageInfo | null>(null)
const loading = ref(false)

export function useFeaturesSettings() {
  const initFeatures = async () => {
    loading.value = true
    try {
      const response = await fetch('/api/package-info')
      const data = await response.json()
      packageInfo.value = data
    } catch (error) {
      console.error('Failed to load features settings:', error)
    } finally {
      loading.value = false
    }
  }

  const isFeatureEnabled = (feature: string): boolean => {
    if (!packageInfo.value) return false
    return packageInfo.value.features.includes(feature)
  }

  const isOcrImportEnabled = computed(() => {
    return packageInfo.value?.is_ocr_import_enabled ?? false
  })

  const getCurrentPackage = computed(() => {
    return packageInfo.value?.current_package ?? 'BASIC'
  })

  onMounted(() => {
    if (!packageInfo.value) {
      initFeatures()
    }
  })

  return {
    packageInfo,
    loading,
    initFeatures,
    isFeatureEnabled,
    isOcrImportEnabled,
    getCurrentPackage,
  }
}
