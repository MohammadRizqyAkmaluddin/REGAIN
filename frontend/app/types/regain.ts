export interface User {
  id: number
  name: string
  email: string
  created_at?: string
}

export interface AccountItem {
  id: number
  name: string
  type: 'cash' | 'bank' | 'ewallet' | 'investment'
  opening_balance: string
  current_balance?: string
  currency: string
  is_default: boolean
}

export interface SubcategoryItem {
  id: number
  category_id: number
  name: string
  slug: string
  is_system: boolean
}

export interface CategoryItem {
  id: number
  name: string
  slug: string
  type: 'income' | 'expense'
  icon: string | null
  color: string | null
  is_system: boolean
  subcategories: SubcategoryItem[]
}

export interface TransactionItem {
  id: number
  account_id: number
  account_name?: string
  category_id: number
  category_name?: string
  category_color?: string
  category_icon?: string
  subcategory_id: number | null
  subcategory_name?: string | null
  type: 'income' | 'expense'
  amount: string
  description: string | null
  transaction_date: string
  transaction_time: string
  source: 'manual' | 'recurring'
  client_uuid?: string | null
  created_at?: string
}

export interface RecurringIncomeItem {
  id: number
  account_id: number
  category_id: number
  subcategory_id: number | null
  name: string
  amount: string
  day_of_month: number
  description: string | null
  start_date: string
  next_run_date: string
  last_executed_at: string | null
  is_active: boolean
  category?: CategoryItem
  account?: AccountItem
}

export interface ExpenseByCategoryItem {
  category_id: number
  category_name: string
  color: string
  total: string
  percentage: number
  transaction_count: number
}

export interface MonthlyTrendItem {
  period: string
  label: string
  income: string
  expenses: string
  net_worth: string
}

export interface DashboardPayload {
  finance: {
    opening_balance: string
    total_actual_income: string
    total_actual_expenses: string
    actual_net_worth: string
    accounts: AccountItem[]
    monthly: {
      period: string
      monthly_income: string
      monthly_expenses: string
      monthly_cashflow: string
      expense_by_category: ExpenseByCategoryItem[]
    }
    trend: MonthlyTrendItem[]
  }
  smoking: {
    current_streak_days: number
    total_smoke_free_days: number
    estimated_money_saved: string
    estimated_packs_avoided: string
    estimated_cigarettes_avoided: number
    monthly_expenditure: string
    yearly_expenditure: string
  }
  recent_transactions: TransactionItem[]
  active_goals: FinancialGoalItem[]
}

export interface SmokingEventItem {
  id: number
  event_type: 'quit_started' | 'relapse' | 'milestone' | 'note'
  event_date: string
  event_time: string
  packs_smoked: string
  estimated_cost: string
  trigger: string | null
  notes: string | null
}

export interface SmokingSummaryPayload {
  profile: {
    id: number
    quit_date: string
    packs_per_week: string
    price_per_pack: string
    cigarettes_per_pack: number
    is_active: boolean
  }
  baseline: {
    daily_expenditure: string
    weekly_expenditure: string
    monthly_expenditure: string
    yearly_expenditure: string
  }
  progress: {
    current_streak_days: number
    total_days_since_quit: number
    total_smoke_free_days: number
    relapse_count: number
    estimated_money_saved: string
    estimated_packs_avoided: string
    estimated_cigarettes_avoided: number
    allocated_to_goals: string
    unallocated_savings: string
  }
  recent_events: SmokingEventItem[]
}

export interface SmokingReportsPayload {
  weekly: Array<{
    label: string
    start_date: string
    end_date: string
    estimated_saved: string
    packs_avoided: string
    smoke_free_days: number
  }>
  monthly: Array<{
    period: string
    label: string
    estimated_saved: string
    packs_avoided: string
    smoke_free_days: number
  }>
}

export interface FinancialGoalItem {
  id: number
  name: string
  category: string
  description: string | null
  target_amount: string
  current_actual_allocation: string
  allocated_smoking_savings: string
  combined_progress_amount: string
  progress_percentage: number
  remaining_amount: string
  target_date: string | null
  icon: string | null
  color: string | null
  status: 'active' | 'completed' | 'paused'
  created_at?: string
}

export interface AppSettingsItem {
  id?: number
  theme: 'dark' | 'light' | 'system'
  locale: string
  timezone: string
  currency: string
  notifications_enabled: boolean
  active_hours_start: string
  active_hours_end: string
  max_daily_notifications: number
  notification_cooldown_minutes: number
}

export interface NotificationLogItem {
  id: number
  template_key: string
  title: string
  body: string
  status: 'queued' | 'sent' | 'failed' | 'skipped'
  scheduled_for: string | null
  sent_at: string | null
  error_message: string | null
  created_at: string
}

export interface OfflinePendingTransaction {
  id?: number
  client_uuid: string
  account_id: number | null
  type: 'income' | 'expense'
  category_id: number
  category_name: string
  subcategory_id: number | null
  subcategory_name: string | null
  amount: string
  description: string
  transaction_date: string
  transaction_time: string
  status: 'pending' | 'syncing' | 'failed'
  error_message?: string
  created_at: string
}
