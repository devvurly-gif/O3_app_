import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
  id: number
  message: string
  type: ToastType
}

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])
  let _id = 0

  function show(message: string, type: ToastType = 'info', duration = 5000) {
    const id = ++_id
    toasts.value.push({ id, message, type })
    if (duration > 0) {
      setTimeout(() => remove(id), duration)
    }
  }

  function remove(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  function success(message: string) {
    show(message, 'success')
  }

  function error(message: string) {
    show(message, 'error', 8000)
  }

  function warning(message: string) {
    show(message, 'warning', 6000)
  }

  function info(message: string) {
    show(message, 'info')
  }

  return { toasts, show, remove, success, error, warning, info }
})
