import { defineStore } from 'pinia'
import type {
  AccountItem,
  CategoryItem,
  DashboardPayload,
  OfflinePendingTransaction,
  RecurringIncomeItem,
  TransactionItem,
} from '~/types/regain'
import { offlineDb } from '~/utils/offlineDb'

export const useFinanceStore = defineStore('finance', () => {
  const dashboard = ref<DashboardPayload | null>(null)
  const accounts = ref<AccountItem[]>([])
  const categories = ref<CategoryItem[]>([])
  const transactions = ref<TransactionItem[]>([])
  const recurringIncomes = ref<RecurringIncomeItem[]>([])
  const pendingOffline = ref<OfflinePendingTransaction[]>([])

  const loadingDashboard = ref(false)
  const loadingTransactions = ref(false)
  const syncingOffline = ref(false)

  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: 0,
  })

  async function loadPendingOffline() {
    if (!import.meta.client) return
    pendingOffline.value = await offlineDb.pendingTransactions.orderBy('created_at').reverse().toArray()
  }

  async function fetchDashboard(month?: string) {
    loadingDashboard.value = true
    const api = useApi()
    try {
      dashboard.value = await api.request<DashboardPayload>('/dashboard', {
        query: month ? { month } : undefined,
      })
      accounts.value = dashboard.value.finance.accounts
    } finally {
      loadingDashboard.value = false
    }
  }

  async function fetchCategories() {
    const api = useApi()
    const res = await api.request<{ data: CategoryItem[] }>('/categories')
    categories.value = res.data
  }

  async function fetchAccounts() {
    const api = useApi()
    const res = await api.request<{ data: AccountItem[] }>('/accounts')
    accounts.value = res.data
  }

  async function fetchTransactions(filters: Record<string, string | number | undefined> = {}) {
    loadingTransactions.value = true
    const api = useApi()
    try {
      const res = await api.request<{
        data: TransactionItem[]
        meta: { current_page: number; last_page: number; per_page: number; total: number }
      }>('/transactions', { query: filters })
      transactions.value = res.data
      pagination.value = res.meta
    } finally {
      loadingTransactions.value = false
    }
  }

  async function fetchRecurringIncomes() {
    const api = useApi()
    const res = await api.request<{ data: RecurringIncomeItem[] }>('/recurring-incomes')
    recurringIncomes.value = res.data
  }

  /**
   * Create a transaction. If offline or network fails, queues it in Dexie.js
   * and NEVER claims it is permanently saved on the server.
   */
  async function createTransaction(payload: {
    account_id: number | null
    type: 'income' | 'expense'
    category_id: number
    subcategory_id: number | null
    amount: number
    description: string
    transaction_date: string
    transaction_time: string
  }): Promise<{ savedOnline: boolean; queuedOffline: boolean }> {
    const clientUuid = crypto.randomUUID()
    const api = useApi()

    if (import.meta.client && !navigator.onLine) {
      await queueOfflineTransaction(clientUuid, payload)
      return { savedOnline: false, queuedOffline: true }
    }

    try {
      await api.request('/transactions', {
        method: 'POST',
        body: {
          ...payload,
          client_uuid: clientUuid,
        },
      })
      return { savedOnline: true, queuedOffline: false }
    } catch (err: any) {
      // Queue offline only on network failures (no HTTP response status)
      if (!err?.response?.status && import.meta.client) {
        await queueOfflineTransaction(clientUuid, payload)
        return { savedOnline: false, queuedOffline: true }
      }
      throw err
    }
  }

  async function queueOfflineTransaction(
    clientUuid: string,
    payload: {
      account_id: number | null
      type: 'income' | 'expense'
      category_id: number
      subcategory_id: number | null
      amount: number
      description: string
      transaction_date: string
      transaction_time: string
    },
  ) {
    const cat = categories.value.find(c => c.id === payload.category_id)
    const sub = cat?.subcategories.find(s => s.id === payload.subcategory_id)

    await offlineDb.pendingTransactions.add({
      client_uuid: clientUuid,
      account_id: payload.account_id,
      type: payload.type,
      category_id: payload.category_id,
      category_name: cat?.name ?? 'Kategori',
      subcategory_id: payload.subcategory_id,
      subcategory_name: sub?.name ?? null,
      amount: payload.amount.toFixed(2),
      description: payload.description,
      transaction_date: payload.transaction_date,
      transaction_time: payload.transaction_time,
      status: 'pending',
      created_at: new Date().toISOString(),
    })

    await loadPendingOffline()
  }

  async function syncPendingOffline(): Promise<{ synced: number; failed: number }> {
    if (!import.meta.client || !navigator.onLine || syncingOffline.value) {
      return { synced: 0, failed: 0 }
    }

    await loadPendingOffline()
    const items = pendingOffline.value.filter(i => i.status === 'pending' || i.status === 'failed')
    if (items.length === 0) return { synced: 0, failed: 0 }

    syncingOffline.value = true
    const api = useApi()
    let synced = 0
    let failed = 0

    try {
      for (const item of items) {
        if (item.id) {
          await offlineDb.pendingTransactions.update(item.id, { status: 'syncing' })
        }
      }
      await loadPendingOffline()

      const res = await api.request<{
        results: Array<{ client_uuid: string; status: 'synced' | 'failed'; error?: string }>
      }>('/transactions/sync', {
        method: 'POST',
        body: {
          transactions: items.map(i => ({
            client_uuid: i.client_uuid,
            account_id: i.account_id,
            type: i.type,
            category_id: i.category_id,
            subcategory_id: i.subcategory_id,
            amount: Number.parseFloat(i.amount),
            description: i.description,
            transaction_date: i.transaction_date,
            transaction_time: i.transaction_time,
          })),
        },
      })

      for (const r of res.results) {
        const local = items.find(i => i.client_uuid === r.client_uuid)
        if (!local?.id) continue
        if (r.status === 'synced') {
          await offlineDb.pendingTransactions.delete(local.id)
          synced++
        } else {
          await offlineDb.pendingTransactions.update(local.id, {
            status: 'failed',
            error_message: r.error ?? 'Gagal sinkronisasi',
          })
          failed++
        }
      }

      await loadPendingOffline()
      if (synced > 0) {
        await fetchDashboard()
      }
    } catch {
      for (const item of items) {
        if (item.id) {
          await offlineDb.pendingTransactions.update(item.id, { status: 'pending' })
        }
      }
      await loadPendingOffline()
    } finally {
      syncingOffline.value = false
    }

    return { synced, failed }
  }

  async function removePendingOffline(id: number) {
    await offlineDb.pendingTransactions.delete(id)
    await loadPendingOffline()
  }

  async function updateTransaction(id: number, payload: Record<string, unknown>) {
    const api = useApi()
    await api.request(`/transactions/${id}`, { method: 'PUT', body: payload })
  }

  async function deleteTransaction(id: number) {
    const api = useApi()
    await api.request(`/transactions/${id}`, { method: 'DELETE' })
  }

  async function createRecurringIncome(payload: Record<string, unknown>) {
    const api = useApi()
    await api.request('/recurring-incomes', { method: 'POST', body: payload })
    await fetchRecurringIncomes()
  }

  async function deleteRecurringIncome(id: number) {
    const api = useApi()
    await api.request(`/recurring-incomes/${id}`, { method: 'DELETE' })
    await fetchRecurringIncomes()
  }

  async function runDueRecurringIncomes() {
    const api = useApi()
    const res = await api.request<{ processed_count: number; message: string }>('/recurring-incomes/run', {
      method: 'POST',
    })
    await Promise.all([fetchRecurringIncomes(), fetchDashboard()])
    return res
  }

  return {
    dashboard,
    accounts,
    categories,
    transactions,
    recurringIncomes,
    pendingOffline,
    pagination,
    loadingDashboard,
    loadingTransactions,
    syncingOffline,
    loadPendingOffline,
    fetchDashboard,
    fetchCategories,
    fetchAccounts,
    fetchTransactions,
    fetchRecurringIncomes,
    createTransaction,
    syncPendingOffline,
    removePendingOffline,
    updateTransaction,
    deleteTransaction,
    createRecurringIncome,
    deleteRecurringIncome,
    runDueRecurringIncomes,
  }
})
