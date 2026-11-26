import { defineStore } from 'pinia'
import type { SmokingReportsPayload, SmokingSummaryPayload } from '~/types/regain'

export const useSmokingStore = defineStore('smoking', () => {
  const summary = ref<SmokingSummaryPayload | null>(null)
  const reports = ref<SmokingReportsPayload | null>(null)
  const loading = ref(false)

  async function fetchSummary() {
    loading.value = true
    const api = useApi()
    try {
      summary.value = await api.request<SmokingSummaryPayload>('/smoking/summary')
    } finally {
      loading.value = false
    }
  }

  async function fetchReports() {
    const api = useApi()
    reports.value = await api.request<SmokingReportsPayload>('/smoking/reports')
  }

  async function updateProfile(payload: {
    quit_date: string
    packs_per_week: number
    price_per_pack: number
    cigarettes_per_pack: number
  }) {
    const api = useApi()
    summary.value = await api.request<SmokingSummaryPayload>('/smoking/profile', {
      method: 'PUT',
      body: payload,
    })
    await fetchReports()
  }

  async function recordRelapse(payload: {
    event_date?: string
    packs_smoked: number
    trigger?: string
    notes?: string
  }) {
    const api = useApi()
    const res = await api.request<{ message: string; summary: SmokingSummaryPayload }>('/smoking/relapse', {
      method: 'POST',
      body: payload,
    })
    summary.value = res.summary
    await fetchReports()
    return res
  }

  return {
    summary,
    reports,
    loading,
    fetchSummary,
    fetchReports,
    updateProfile,
    recordRelapse,
  }
})
