<template>
  <div class="stats-card" :class="{ [variant]: true }">
    <div class="card-header">
      <h3 class="card-title">{{ title }}</h3>
      <div v-if="trend" class="trend-badge" :class="trendClass">
        {{ trendIcon }} {{ Math.abs(trend) }}%
      </div>
    </div>

    <div class="card-content">
      <div class="value">{{ formattedValue }}</div>
      <div v-if="subtitle" class="subtitle">{{ subtitle }}</div>
    </div>

    <div v-if="footer" class="card-footer">
      {{ footer }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  title: string
  value: number | string
  subtitle?: string
  footer?: string
  trend?: number
  variant?: 'default' | 'success' | 'warning' | 'danger' | 'info'
  format?: 'number' | 'currency' | 'percentage'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
  format: 'number',
})

const formattedValue = computed(() => {
  const num = typeof props.value === 'string' ? parseFloat(props.value) : props.value

  switch (props.format) {
    case 'currency':
      return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'MAD',
        maximumFractionDigits: 0,
      }).format(num)
    case 'percentage':
      return `${num.toFixed(1)}%`
    default:
      return new Intl.NumberFormat('fr-FR').format(num)
  }
})

const trendClass = computed(() => {
  if (!props.trend) return ''
  return props.trend > 0 ? 'trend-up' : 'trend-down'
})

const trendIcon = computed(() => {
  if (!props.trend) return ''
  return props.trend > 0 ? '📈' : '📉'
})
</script>

<style scoped>
.stats-card {
  padding: 1.5rem;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
  background: white;
  transition: all 0.2s;
}

.stats-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.stats-card.success {
  border-color: #d1fae5;
  background-color: #f0fdf4;
}

.stats-card.warning {
  border-color: #fef3c7;
  background-color: #fffbeb;
}

.stats-card.danger {
  border-color: #fee2e2;
  background-color: #fef2f2;
}

.stats-card.info {
  border-color: #dbeafe;
  background-color: #f0f9ff;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.card-title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 500;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.trend-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.25rem 0.75rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.trend-up {
  background-color: #dcfce7;
  color: #166534;
}

.trend-down {
  background-color: #fee2e2;
  color: #991b1b;
}

.card-content {
  margin-bottom: 1rem;
}

.value {
  font-size: 2rem;
  font-weight: 700;
  color: #1f2937;
  line-height: 1.2;
}

.subtitle {
  margin-top: 0.5rem;
  font-size: 0.875rem;
  color: #9ca3af;
}

.card-footer {
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
  font-size: 0.75rem;
  color: #9ca3af;
}

/* Dark mode */
:global(.dark) .stats-card {
  background: #1f2937;
  border-color: #374151;
}

:global(.dark) .stats-card.success {
  background-color: #064e3b;
  border-color: #047857;
}

:global(.dark) .stats-card.warning {
  background-color: #78350f;
  border-color: #a16207;
}

:global(.dark) .stats-card.danger {
  background-color: #7f1d1d;
  border-color: #dc2626;
}

:global(.dark) .stats-card.info {
  background-color: #0c2d48;
  border-color: #0284c7;
}

:global(.dark) .card-title {
  color: #d1d5db;
}

:global(.dark) .value {
  color: #f3f4f6;
}

:global(.dark) .subtitle {
  color: #9ca3af;
}

:global(.dark) .card-footer {
  border-color: #374151;
  color: #9ca3af;
}
</style>
