import { defineStore } from 'pinia'
import type { FinancialGoalItem } from '~/types/regain'

export const useGoalsStore = defineStore('goals', () => {
  const goals = ref<FinancialGoalItem[]>([])
  const pool = ref({
    estimated_money_saved: '0.00',
    allocated_to_goals: '0.00',
    unallocated_savings: '0.00',
  })
  const loading = ref(false)

  async function fetchGoals() {
    loading.value = true
    const api = useApi()
    try {
      const res = await api.request<{
        data: FinancialGoalItem[]
        smoking_savings_pool: {
          estimated_money_saved: string
          allocated_to_goals: string
          unallocated_savings: string
        }
      }>('/goals')
      goals.value = res.data
      pool.value = res.smoking_savings_pool
    } finally {
      loading.value = false
    }
  }

  async function createGoal(payload: Record<string, unknown>) {
    const api = useApi()
    await api.request('/goals', { method: 'POST', body: payload })
    await fetchGoals()
  }

  async function updateGoal(id: number, payload: Record<string, unknown>) {
    const api = useApi()
    await api.request(`/goals/${id}`, { method: 'PUT', body: payload })
    await fetchGoals()
  }

  async function allocateGoal(
    id: number,
    payload: { allocated_smoking_savings?: number; current_actual_allocation?: number },
  ) {
    const api = useApi()
    await api.request(`/goals/${id}/allocate`, { method: 'POST', body: payload })
    await fetchGoals()
  }

  async function deleteGoal(id: number) {
    const api = useApi()
    await api.request(`/goals/${id}`, { method: 'DELETE' })
    await fetchGoals()
  }

  return {
    goals,
    pool,
    loading,
    fetchGoals,
    createGoal,
    updateGoal,
    allocateGoal,
    deleteGoal,
  }
})
