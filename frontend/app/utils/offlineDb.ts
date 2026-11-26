import Dexie, { type Table } from 'dexie'
import type { OfflinePendingTransaction } from '~/types/regain'

class RegainOfflineDB extends Dexie {
  pendingTransactions!: Table<OfflinePendingTransaction, number>

  constructor() {
    super('regain_offline_db')
    this.version(1).stores({
      pendingTransactions: '++id, &client_uuid, status, created_at',
    })
  }
}

export const offlineDb = new RegainOfflineDB()
