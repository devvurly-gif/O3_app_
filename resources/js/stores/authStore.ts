import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import http from '@/services/http'
import type { User, LoginResponse } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  // ── State ────────────────────────────────────────────────────────────────
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('token') ?? null)

  // ── Getters ──────────────────────────────────────────────────────────────
  const isAuthenticated = computed(() => !!token.value)
  const userName = computed(() => user.value?.name ?? '')
  const userEmail = computed(() => user.value?.email ?? '')
  const userRole = computed(() => user.value?.role ?? '')
  const userAvatar = computed(() => user.value?.avatar ?? null)
  const userPermissions = computed(() => user.value?.permissions ?? [])
  const activeModules = computed(() => user.value?.active_modules ?? [])
  const isAdmin = computed(() => user.value?.role === 'admin')

  function hasPermission(permission: string): boolean {
    if (isAdmin.value) return true
    return userPermissions.value.includes(permission)
  }

  function hasAnyPermission(...permissions: string[]): boolean {
    if (isAdmin.value) return true
    return permissions.some((p) => userPermissions.value.includes(p))
  }

  function hasModule(slug: string): boolean {
    return activeModules.value.includes(slug)
  }

  // ── Helpers ──────────────────────────────────────────────────────────────
  function setToken(raw: string | null): void {
    token.value = raw
    if (raw) localStorage.setItem('token', raw)
    else localStorage.removeItem('token')
  }

  function setUser(data: User | null): void {
    user.value = data ?? null
  }

  // ── Actions ──────────────────────────────────────────────────────────────
  async function login(email: string, password: string): Promise<LoginResponse> {
    const { data } = await http.post<LoginResponse>('/auth/login', { email, password })
    setToken(data.token)
    setUser(data.user)
    return data
  }

  async function logout(): Promise<void> {
    try {
      await http.post('/auth/logout')
    } catch {
      // swallow — token may already be invalid
    } finally {
      setToken(null)
      setUser(null)
    }
  }

  async function fetchMe(): Promise<void> {
    if (!token.value) return
    try {
      const { data } = await http.get<User>('/auth/me')
      setUser(data)
    } catch {
      setToken(null)
      setUser(null)
    }
  }

  return {
    // state
    user,
    token,
    // getters
    isAuthenticated,
    isAdmin,
    userName,
    userEmail,
    userRole,
    userAvatar,
    userPermissions,
    activeModules,
    // permission helpers
    hasPermission,
    hasAnyPermission,
    hasModule,
    // actions
    login,
    logout,
    fetchMe,
  }
})
