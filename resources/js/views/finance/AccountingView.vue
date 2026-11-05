<template>
  <div>
    <!-- Standard Header Card -->
    <v-card class="elevation-1 rounded-lg pa-4 mb-4" color="white">
      <div class="d-flex flex-wrap justify-space-between align-center ga-3">
        <div class="d-flex align-center">
          <v-avatar color="green-darken-2" class="mr-3" size="44">
            <v-icon icon="mdi-book-open-outline" color="white" size="24"></v-icon>
          </v-avatar>
          <div>
            <h1 class="text-h6 font-weight-bold">Financial Accounting & General Ledger</h1>
            <p class="text-caption text-grey">Double-entry ledger, true batch COGS, real-time P&L, and Balance Sheet</p>
          </div>
        </div>

        <div class="d-flex flex-wrap ga-2">
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            @click="openManualEntryModal"
            class="text-none font-weight-medium"
          >
            Post Journal Entry
          </v-btn>
          <v-btn
            color="green-darken-3"
            variant="outlined"
            prepend-icon="mdi-bank-plus"
            @click="showAddAccountModal = true"
            class="text-none font-weight-medium"
          >
            Add Account
          </v-btn>
          <v-btn
            icon="mdi-refresh"
            variant="text"
            @click="loadActiveTabData"
            :loading="loading"
            title="Refresh financial data"
          ></v-btn>
        </div>
      </div>
    </v-card>

    <!-- Quick Financial Health KPI Cards (Uniform height & structure) -->
    <v-row density="compact" class="mb-4">
      <v-col cols="12" sm="6" md="3">
        <v-card
          class="elevation-1 rounded-lg pa-4 border-s-lg border-teal h-100 d-flex flex-column justify-center cursor-default"
          style="min-height: 104px;"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <p class="text-caption text-teal-darken-3 font-weight-bold mb-1">TOTAL SALES REVENUE</p>
              <h3 class="text-h6 font-weight-bold text-teal-darken-4">
                KES {{ formatNumber(pnlData?.revenue?.total_revenue || 0) }}
              </h3>
              <p class="text-caption text-grey">Gross dispensing income</p>
            </div>
            <v-avatar color="teal-lighten-5" size="44">
              <v-icon icon="mdi-cash-register" color="teal-darken-2" size="24"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card
          class="elevation-1 rounded-lg pa-4 border-s-lg border-blue h-100 d-flex flex-column justify-center cursor-default"
          style="min-height: 104px;"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <p class="text-caption text-blue-darken-3 font-weight-bold mb-1">GROSS PROFIT (AFTER COGS)</p>
              <h3 class="text-h6 font-weight-bold text-blue-darken-4">
                KES {{ formatNumber(pnlData?.gross_profit || 0) }}
              </h3>
              <p class="text-caption text-blue-darken-2 font-weight-bold">
                Margin: {{ pnlData?.gross_margin_percent || 0 }}%
              </p>
            </div>
            <v-avatar color="blue-lighten-5" size="44">
              <v-icon icon="mdi-scale-balance" color="blue-darken-2" size="24"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card
          class="elevation-1 rounded-lg pa-4 border-s-lg border-deep-orange h-100 d-flex flex-column justify-center cursor-default"
          style="min-height: 104px;"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <p class="text-caption text-deep-orange font-weight-bold mb-1">OPERATING EXPENSES</p>
              <h3 class="text-h6 font-weight-bold text-deep-orange-darken-3">
                KES {{ formatNumber(pnlData?.operating_expenses?.total_expenses || 0) }}
              </h3>
              <p class="text-caption text-grey">Rent, salaries & overheads</p>
            </div>
            <v-avatar color="deep-orange-lighten-5" size="44">
              <v-icon icon="mdi-receipt-text-outline" color="deep-orange" size="24"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card
          class="elevation-1 rounded-lg pa-4 border-s-lg h-100 d-flex flex-column justify-center cursor-default"
          :class="(pnlData?.net_operating_profit || 0) >= 0 ? 'border-success' : 'border-error'"
          style="min-height: 104px;"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <p class="text-caption font-weight-bold mb-1" :class="(pnlData?.net_operating_profit || 0) >= 0 ? 'text-success' : 'text-error'">
                NET OPERATING PROFIT
              </p>
              <h3 class="text-h6 font-weight-bold" :class="(pnlData?.net_operating_profit || 0) >= 0 ? 'text-success' : 'text-error'">
                KES {{ formatNumber(pnlData?.net_operating_profit || 0) }}
              </h3>
              <p class="text-caption font-weight-bold" :class="(pnlData?.net_operating_profit || 0) >= 0 ? 'text-success' : 'text-error'">
                Net Margin: {{ pnlData?.net_margin_percent || 0 }}%
              </p>
            </div>
            <v-avatar :color="(pnlData?.net_operating_profit || 0) >= 0 ? 'green-lighten-5' : 'red-lighten-5'" size="44">
              <v-icon :icon="(pnlData?.net_operating_profit || 0) >= 0 ? 'mdi-trending-up' : 'mdi-trending-down'" :color="(pnlData?.net_operating_profit || 0) >= 0 ? 'success' : 'error'" size="24"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Main Navigation Tabs -->
    <v-tabs v-model="tab" color="primary" class="mb-4 bg-white rounded-lg elevation-1 px-2" @update:model-value="onTabChange">
      <v-tab value="pnl" class="text-none">
        <v-icon start icon="mdi-chart-line"></v-icon>
        Profit & Loss Statement
      </v-tab>
      <v-tab value="balance-sheet" class="text-none">
        <v-icon start icon="mdi-scale-balance"></v-icon>
        Balance Sheet
      </v-tab>
      <v-tab value="chart-of-accounts" class="text-none">
        <v-icon start icon="mdi-format-list-numbered"></v-icon>
        Chart of Accounts
      </v-tab>
      <v-tab value="journal-entries" class="text-none">
        <v-icon start icon="mdi-book-multiple-outline"></v-icon>
        General Ledger / Journals
      </v-tab>
      <v-tab value="trial-balance" class="text-none">
        <v-icon start icon="mdi-clipboard-check-outline"></v-icon>
        Trial Balance
      </v-tab>
      <v-tab value="cash-flow" class="text-none">
        <v-icon start icon="mdi-cash-fast"></v-icon>
        Cash Flow
      </v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <!-- TAB 1: PROFIT & LOSS STATEMENT -->
      <v-window-item value="pnl">
        <v-card class="elevation-1 rounded-lg pa-4">
          <!-- Date Filter Header -->
          <div class="d-flex flex-wrap justify-space-between align-center mb-4 pb-3 border-b ga-3">
            <div>
              <h2 class="text-subtitle-1 font-weight-bold text-grey-darken-3">Income Statement (Profit & Loss)</h2>
              <p class="text-caption text-grey">Period: {{ pnlData?.period?.start_date }} to {{ pnlData?.period?.end_date }}</p>
            </div>
            <div class="d-flex align-center ga-2">
              <v-text-field
                v-model="pnlStartDate"
                type="date"
                label="Start Date"
                variant="outlined"
                density="compact"
                hide-details
                style="max-width: 160px;"
              ></v-text-field>
              <v-text-field
                v-model="pnlEndDate"
                type="date"
                label="End Date"
                variant="outlined"
                density="compact"
                hide-details
                style="max-width: 160px;"
              ></v-text-field>
              <v-btn color="primary" variant="tonal" size="small" @click="loadPnl" :loading="loadingPnl">
                Generate
              </v-btn>
            </div>
          </div>

          <!-- Statement Content -->
          <div class="pnl-statement font-mono text-body-2">
            <!-- 1. REVENUE SECTION -->
            <div class="pa-3 bg-grey-lighten-4 rounded mb-2 font-weight-bold text-teal-darken-4 d-flex justify-space-between">
              <span>1. OPERATING REVENUE</span>
              <span>KES {{ formatNumber(pnlData?.revenue?.total_revenue || 0) }}</span>
            </div>
            <div class="px-4 py-1 d-flex justify-space-between text-caption text-grey-darken-2">
              <span>Pharmaceutical Sales & Retail Dispensing</span>
              <span>KES {{ formatNumber(pnlData?.revenue?.sales_revenue || 0) }}</span>
            </div>

            <!-- 2. COST OF SALES SECTION -->
            <div class="pa-3 bg-grey-lighten-4 rounded mt-4 mb-2 font-weight-bold text-deep-orange-darken-4 d-flex justify-space-between">
              <span>2. COST OF GOODS SOLD (COGS)</span>
              <span>(KES {{ formatNumber(pnlData?.cost_of_sales?.total_cogs || 0) }})</span>
            </div>
            <div class="px-4 py-1 d-flex justify-space-between text-caption text-grey-darken-2">
              <span>Historical Cost of Dispensed Product Batches (FEFO)</span>
              <span>KES {{ formatNumber(pnlData?.cost_of_sales?.cost_of_goods_sold || 0) }}</span>
            </div>

            <!-- GROSS PROFIT ROW -->
            <div class="pa-3 bg-blue-lighten-5 rounded my-3 font-weight-bold text-blue-darken-4 d-flex justify-space-between text-body-1 border">
              <span>GROSS PROFIT (Margin: {{ pnlData?.gross_margin_percent || 0 }}%)</span>
              <span>KES {{ formatNumber(pnlData?.gross_profit || 0) }}</span>
            </div>

            <!-- 3. OPERATING EXPENSES -->
            <div class="pa-3 bg-grey-lighten-4 rounded mt-4 mb-2 font-weight-bold text-grey-darken-4 d-flex justify-space-between">
              <span>3. OPERATING EXPENDITURE</span>
              <span>(KES {{ formatNumber(pnlData?.operating_expenses?.total_expenses || 0) }})</span>
            </div>
            <div
              v-for="exp in pnlData?.operating_expenses?.items || []"
              :key="exp.account_code"
              class="px-4 py-1 d-flex justify-space-between text-caption text-grey-darken-2 border-b"
            >
              <span>{{ exp.account_code }} - {{ exp.account_name }}</span>
              <span>KES {{ formatNumber(exp.amount) }}</span>
            </div>
            <div v-if="!pnlData?.operating_expenses?.items?.length" class="px-4 py-2 text-caption text-grey">
              No operating expenses recorded for this period.
            </div>

            <!-- NET PROFIT ROW -->
            <div
              class="pa-4 rounded my-4 font-weight-bold d-flex justify-space-between text-h6 border"
              :class="(pnlData?.net_operating_profit || 0) >= 0 ? 'bg-green-lighten-5 text-green-darken-4' : 'bg-red-lighten-5 text-red-darken-4'"
            >
              <span>NET OPERATING PROFIT (EBIT)</span>
              <span>KES {{ formatNumber(pnlData?.net_operating_profit || 0) }}</span>
            </div>
          </div>
        </v-card>
      </v-window-item>

      <!-- TAB 2: BALANCE SHEET -->
      <v-window-item value="balance-sheet">
        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="d-flex flex-wrap justify-space-between align-center mb-4 pb-3 border-b ga-3">
            <div>
              <h2 class="text-subtitle-1 font-weight-bold text-grey-darken-3">Statement of Financial Position (Balance Sheet)</h2>
              <p class="text-caption text-grey">As of {{ balanceSheetData?.as_of_date || 'Today' }}</p>
            </div>
            <v-chip
              size="small"
              :color="balanceSheetData?.is_balanced ? 'success' : 'error'"
              variant="flat"
              class="font-weight-bold"
            >
              <v-icon start :icon="balanceSheetData?.is_balanced ? 'mdi-check-circle' : 'mdi-alert-circle'"></v-icon>
              {{ balanceSheetData?.is_balanced ? 'Equilibrium Balanced (Assets = Liabilities + Equity)' : 'Unbalanced' }}
            </v-chip>
          </div>

          <v-row density="compact">
            <!-- ASSETS COLUMN -->
            <v-col cols="12" md="6">
              <v-card variant="outlined" class="pa-3 rounded-lg h-100">
                <div class="pa-2 bg-teal-lighten-5 rounded font-weight-bold text-teal-darken-4 d-flex justify-space-between mb-2">
                  <span>TOTAL ASSETS</span>
                  <span>KES {{ formatNumber(balanceSheetData?.assets?.total_assets || 0) }}</span>
                </div>
                <div class="text-caption font-weight-bold text-grey mb-1">CURRENT ASSETS</div>
                <div
                  v-for="a in balanceSheetData?.assets?.current_assets || []"
                  :key="a.code"
                  class="d-flex justify-space-between py-1 border-b text-caption font-mono"
                >
                  <span>{{ a.code }} - {{ a.name }}</span>
                  <span class="font-weight-bold">KES {{ formatNumber(a.amount) }}</span>
                </div>
              </v-card>
            </v-col>

            <!-- LIABILITIES & EQUITY COLUMN -->
            <v-col cols="12" md="6">
              <v-card variant="outlined" class="pa-3 rounded-lg mb-3">
                <div class="pa-2 bg-amber-lighten-5 rounded font-weight-bold text-amber-darken-4 d-flex justify-space-between mb-2">
                  <span>TOTAL LIABILITIES</span>
                  <span>KES {{ formatNumber(balanceSheetData?.liabilities?.total_liabilities || 0) }}</span>
                </div>
                <div class="text-caption font-weight-bold text-grey mb-1">CURRENT LIABILITIES</div>
                <div
                  v-for="l in balanceSheetData?.liabilities?.current_liabilities || []"
                  :key="l.code"
                  class="d-flex justify-space-between py-1 border-b text-caption font-mono"
                >
                  <span>{{ l.code }} - {{ l.name }}</span>
                  <span class="font-weight-bold">KES {{ formatNumber(l.amount) }}</span>
                </div>
              </v-card>

              <v-card variant="outlined" class="pa-3 rounded-lg">
                <div class="pa-2 bg-purple-lighten-5 rounded font-weight-bold text-purple-darken-4 d-flex justify-space-between mb-2">
                  <span>TOTAL EQUITY</span>
                  <span>KES {{ formatNumber(balanceSheetData?.equity?.total_equity || 0) }}</span>
                </div>
                <div
                  v-for="e in balanceSheetData?.equity?.items || []"
                  :key="e.code"
                  class="d-flex justify-space-between py-1 border-b text-caption font-mono"
                >
                  <span>{{ e.name }}</span>
                  <span class="font-weight-bold">KES {{ formatNumber(e.amount) }}</span>
                </div>
              </v-card>
            </v-col>
          </v-row>
        </v-card>
      </v-window-item>

      <!-- TAB 3: CHART OF ACCOUNTS -->
      <v-window-item value="chart-of-accounts">
        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="d-flex flex-wrap justify-space-between align-center mb-3 ga-3">
            <div class="d-flex ga-1 flex-wrap">
              <v-chip
                v-for="t in ['all', 'asset', 'liability', 'equity', 'revenue', 'expense']"
                :key="t"
                size="small"
                :color="selectedTypeFilter === t ? 'primary' : 'grey'"
                :variant="selectedTypeFilter === t ? 'flat' : 'outlined'"
                @click="filterAccountType(t)"
                class="text-capitalize font-weight-bold"
              >
                {{ t }}
              </v-chip>
            </div>
            <v-btn color="primary" size="small" variant="tonal" prepend-icon="mdi-plus" @click="showAddAccountModal = true">
              Add Ledger Account
            </v-btn>
          </div>

          <v-table hover density="comfortable">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th class="font-weight-bold">Code</th>
                <th class="font-weight-bold">Account Name</th>
                <th class="font-weight-bold">Type</th>
                <th class="font-weight-bold">Subtype</th>
                <th class="font-weight-bold text-end">Current Balance</th>
                <th class="font-weight-bold text-center">System Lock</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="acc in filteredAccounts" :key="acc.id">
                <td class="font-mono font-weight-bold text-primary">{{ acc.code }}</td>
                <td class="font-weight-medium">
                  {{ acc.name }}
                  <div class="text-caption text-grey">{{ acc.description }}</div>
                </td>
                <td>
                  <v-chip size="x-small" :color="getTypeColor(acc.type)" variant="tonal" class="text-capitalize font-weight-bold">
                    {{ acc.type }}
                  </v-chip>
                </td>
                <td class="text-caption text-grey text-capitalize">{{ acc.subtype?.replace('_', ' ') || '—' }}</td>
                <td class="text-end font-mono font-weight-bold text-body-2">
                  KES {{ formatNumber(acc.current_balance) }}
                </td>
                <td class="text-center">
                  <v-icon v-if="acc.is_system" icon="mdi-lock" size="16" color="grey" title="System core account"></v-icon>
                  <span v-else class="text-caption text-grey">Custom</span>
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <!-- TAB 4: GENERAL LEDGER / JOURNAL ENTRIES -->
      <v-window-item value="journal-entries">
        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="d-flex justify-space-between align-center mb-3">
            <div>
              <h2 class="text-subtitle-1 font-weight-bold text-grey-darken-3">Audit-Logged Journal Entries</h2>
              <p class="text-caption text-grey">Double-entry balanced transaction ledger</p>
            </div>
            <v-btn color="primary" size="small" prepend-icon="mdi-plus" @click="openManualEntryModal">
              New Journal Entry
            </v-btn>
          </div>

          <v-expansion-panels variant="accordion">
            <v-expansion-panel v-for="entry in journalEntries" :key="entry.id" class="border mb-2 rounded">
              <v-expansion-panel-title class="py-2">
                <div class="d-flex justify-space-between align-center w-100 pr-4">
                  <div class="d-flex align-center">
                    <span class="font-mono font-weight-bold text-primary mr-3">{{ entry.entry_number }}</span>
                    <v-chip size="x-small" color="blue" variant="outlined" class="text-capitalize mr-3">
                      {{ entry.reference_type?.replace('_', ' ') }}
                    </v-chip>
                    <span class="text-body-2 font-weight-medium">{{ entry.description }}</span>
                  </div>
                  <div class="d-flex align-center ga-4 font-mono text-caption">
                    <span class="text-grey">{{ formatDateTime(entry.entry_date) }}</span>
                    <span class="font-weight-bold text-teal-darken-3">KES {{ formatNumber(entry.total_debit) }}</span>
                  </div>
                </div>
              </v-expansion-panel-title>
              <v-expansion-panel-text>
                <v-table density="compact" class="bg-grey-lighten-5 rounded">
                  <thead>
                    <tr>
                      <th class="font-weight-bold">Account</th>
                      <th class="font-weight-bold">Description</th>
                      <th class="font-weight-bold text-end">Debit (Dr)</th>
                      <th class="font-weight-bold text-end">Credit (Cr)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="line in entry.lines" :key="line.id">
                      <td>
                        <span class="font-mono text-primary font-weight-bold mr-1">[{{ line.account?.code }}]</span>
                        <span>{{ line.account?.name }}</span>
                      </td>
                      <td class="text-caption text-grey">{{ line.description }}</td>
                      <td class="text-end font-mono font-weight-bold">
                        {{ line.debit > 0 ? 'KES ' + formatNumber(line.debit) : '—' }}
                      </td>
                      <td class="text-end font-mono font-weight-bold">
                        {{ line.credit > 0 ? 'KES ' + formatNumber(line.credit) : '—' }}
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="font-weight-bold border-t">
                      <td colspan="2">TOTALS</td>
                      <td class="text-end font-mono text-teal-darken-3">KES {{ formatNumber(entry.total_debit) }}</td>
                      <td class="text-end font-mono text-teal-darken-3">KES {{ formatNumber(entry.total_credit) }}</td>
                    </tr>
                  </tfoot>
                </v-table>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-card>
      </v-window-item>

      <!-- TAB 5: TRIAL BALANCE -->
      <v-window-item value="trial-balance">
        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="d-flex justify-space-between align-center mb-3">
            <div>
              <h2 class="text-subtitle-1 font-weight-bold text-grey-darken-3">General Ledger Trial Balance</h2>
              <p class="text-caption text-grey">As of {{ trialBalanceData?.as_of_date || 'Today' }}</p>
            </div>
            <v-chip
              size="small"
              :color="trialBalanceData?.is_balanced ? 'success' : 'error'"
              variant="flat"
              class="font-weight-bold"
            >
              {{ trialBalanceData?.is_balanced ? 'Trial Balance Verified (Debit == Credit)' : 'Discrepancy Detected' }}
            </v-chip>
          </div>

          <v-table hover density="comfortable">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th class="font-weight-bold">Account Code</th>
                <th class="font-weight-bold">Account Name</th>
                <th class="font-weight-bold">Type</th>
                <th class="font-weight-bold text-end">Debit (Dr)</th>
                <th class="font-weight-bold text-end">Credit (Cr)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in trialBalanceData?.accounts || []" :key="row.id">
                <td class="font-mono font-weight-bold text-primary">{{ row.code }}</td>
                <td class="font-weight-medium">{{ row.name }}</td>
                <td>
                  <v-chip size="x-small" :color="getTypeColor(row.type)" variant="tonal" class="text-capitalize">
                    {{ row.type }}
                  </v-chip>
                </td>
                <td class="text-end font-mono font-weight-bold">
                  {{ row.debit > 0 ? 'KES ' + formatNumber(row.debit) : '—' }}
                </td>
                <td class="text-end font-mono font-weight-bold">
                  {{ row.credit > 0 ? 'KES ' + formatNumber(row.credit) : '—' }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="bg-grey-lighten-3 font-weight-bold text-body-2">
                <td colspan="3">BALANCED TOTALS</td>
                <td class="text-end font-mono text-teal-darken-4">
                  KES {{ formatNumber(trialBalanceData?.total_debit || 0) }}
                </td>
                <td class="text-end font-mono text-teal-darken-4">
                  KES {{ formatNumber(trialBalanceData?.total_credit || 0) }}
                </td>
              </tr>
            </tfoot>
          </v-table>
        </v-card>
      </v-window-item>

      <!-- TAB 6: CASH FLOW -->
      <v-window-item value="cash-flow">
        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="text-subtitle-1 font-weight-bold text-grey-darken-3 mb-3">Operating Cash Flow Summary</div>
          <v-row density="compact">
            <v-col cols="12" md="6">
              <v-card variant="outlined" class="pa-3 rounded-lg mb-3">
                <div class="pa-2 bg-green-lighten-5 rounded font-weight-bold text-green-darken-4 d-flex justify-space-between mb-2">
                  <span>CASH INFLOWS</span>
                  <span>KES {{ formatNumber(cashFlowData?.inflows?.total_inflows || 0) }}</span>
                </div>
                <div class="d-flex justify-space-between py-1 border-b text-caption font-mono">
                  <span>POS Counter Cash & M-Pesa Collections</span>
                  <span class="font-weight-bold">KES {{ formatNumber(cashFlowData?.inflows?.pos_cash_and_mpesa_sales || 0) }}</span>
                </div>
              </v-card>
            </v-col>

            <v-col cols="12" md="6">
              <v-card variant="outlined" class="pa-3 rounded-lg mb-3">
                <div class="pa-2 bg-red-lighten-5 rounded font-weight-bold text-red-darken-4 d-flex justify-space-between mb-2">
                  <span>CASH OUTFLOWS</span>
                  <span>KES {{ formatNumber(cashFlowData?.outflows?.total_outflows || 0) }}</span>
                </div>
                <div class="d-flex justify-space-between py-1 border-b text-caption font-mono">
                  <span>Supplier Invoices Settled (Accounts Payable)</span>
                  <span class="font-weight-bold">KES {{ formatNumber(cashFlowData?.outflows?.supplier_payments || 0) }}</span>
                </div>
                <div class="d-flex justify-space-between py-1 border-b text-caption font-mono">
                  <span>Operating Overheads (Vouchers)</span>
                  <span class="font-weight-bold">KES {{ formatNumber(cashFlowData?.outflows?.operating_expenses || 0) }}</span>
                </div>
              </v-card>
            </v-col>
          </v-row>

          <div
            class="pa-4 rounded font-weight-bold d-flex justify-space-between text-h6 border mt-2"
            :class="(cashFlowData?.net_cash_flow || 0) >= 0 ? 'bg-green-lighten-5 text-green-darken-4' : 'bg-red-lighten-5 text-red-darken-4'"
          >
            <span>NET OPERATING CASH FLOW</span>
            <span>KES {{ formatNumber(cashFlowData?.net_cash_flow || 0) }}</span>
          </div>
        </v-card>
      </v-window-item>
    </v-window>

    <!-- MODAL 1: POST MANUAL JOURNAL ENTRY -->
    <v-dialog v-model="showManualEntryModal" max-width="750px">
      <v-card class="pa-4 rounded-lg">
        <v-card-title class="text-h6 font-weight-bold text-primary">
          Post Double-Entry Journal Transaction
        </v-card-title>
        <v-card-text>
          <v-form @submit.prevent="submitJournalEntry">
            <v-row density="compact" class="mb-2">
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="journalForm.description"
                  label="Transaction Description *"
                  placeholder="e.g. Month-end depreciation, Bank charge correction"
                  variant="outlined"
                  density="compact"
                  required
                ></v-text-field>
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="journalForm.entry_date"
                  type="date"
                  label="Entry Date"
                  variant="outlined"
                  density="compact"
                ></v-text-field>
              </v-col>
            </v-row>

            <div class="text-subtitle-2 font-weight-bold mb-2">Debit & Credit Lines</div>
            <div v-for="(line, idx) in journalForm.lines" :key="idx" class="d-flex align-center ga-2 mb-2">
              <v-select
                v-model="line.account_id"
                :items="accounts"
                item-title="name"
                item-value="id"
                label="Account"
                variant="outlined"
                density="compact"
                style="flex: 2;"
                hide-details
              >
                <template v-slot:item="{ item, props }">
                  <v-list-item v-bind="props" :subtitle="item.raw.code + ' • ' + item.raw.type"></v-list-item>
                </template>
              </v-select>

              <v-text-field
                v-model.number="line.debit"
                type="number"
                step="0.01"
                label="Debit (Dr)"
                variant="outlined"
                density="compact"
                style="flex: 1;"
                hide-details
                @input="line.credit = 0"
              ></v-text-field>

              <v-text-field
                v-model.number="line.credit"
                type="number"
                step="0.01"
                label="Credit (Cr)"
                variant="outlined"
                density="compact"
                style="flex: 1;"
                hide-details
                @input="line.debit = 0"
              ></v-text-field>

              <v-btn
                icon="mdi-close"
                size="x-small"
                variant="text"
                color="error"
                @click="removeJournalLine(idx)"
                :disabled="journalForm.lines.length <= 2"
              ></v-btn>
            </div>

            <v-btn size="small" variant="text" color="primary" prepend-icon="mdi-plus" @click="addJournalLine" class="mb-3">
              Add Line
            </v-btn>

            <!-- Verification Totals -->
            <div class="d-flex justify-space-between align-center pa-3 bg-grey-lighten-4 rounded font-mono font-weight-bold">
              <span>Total Debit: KES {{ formatNumber(manualEntryTotalDebit) }}</span>
              <span>Total Credit: KES {{ formatNumber(manualEntryTotalCredit) }}</span>
              <v-chip
                size="small"
                :color="isManualEntryBalanced ? 'success' : 'error'"
                variant="flat"
              >
                {{ isManualEntryBalanced ? 'Balanced' : 'Unbalanced Diff: KES ' + formatNumber(Math.abs(manualEntryTotalDebit - manualEntryTotalCredit)) }}
              </v-chip>
            </div>

            <v-card-actions class="mt-4 px-0">
              <v-spacer></v-spacer>
              <v-btn variant="text" @click="showManualEntryModal = false">Cancel</v-btn>
              <v-btn
                color="primary"
                type="submit"
                :disabled="!isManualEntryBalanced || manualEntryTotalDebit <= 0"
                :loading="savingJournal"
                class="font-weight-bold"
              >
                Post to General Ledger
              </v-btn>
            </v-card-actions>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL 2: ADD CHART OF ACCOUNTS ITEM -->
    <v-dialog v-model="showAddAccountModal" max-width="500px">
      <v-card class="pa-4 rounded-lg">
        <v-card-title class="text-h6 font-weight-bold text-green-darken-3">
          Create New Ledger Account
        </v-card-title>
        <v-card-text>
          <v-form @submit.prevent="submitAccount">
            <v-text-field
              v-model="accountForm.code"
              label="Account Code (e.g. 1030, 6150) *"
              variant="outlined"
              density="compact"
              required
              class="mb-2"
            ></v-text-field>

            <v-text-field
              v-model="accountForm.name"
              label="Account Name *"
              placeholder="e.g. Courier Transport Expense"
              variant="outlined"
              density="compact"
              required
              class="mb-2"
            ></v-text-field>

            <v-select
              v-model="accountForm.type"
              :items="['asset', 'liability', 'equity', 'revenue', 'expense']"
              label="Account Classification *"
              variant="outlined"
              density="compact"
              required
              class="mb-2 text-capitalize"
            ></v-select>

            <v-text-field
              v-model.number="accountForm.opening_balance"
              type="number"
              step="0.01"
              label="Opening Balance (KES)"
              variant="outlined"
              density="compact"
              class="mb-2"
            ></v-text-field>

            <v-textarea
              v-model="accountForm.description"
              label="Account Purpose & Description"
              rows="2"
              variant="outlined"
              density="compact"
            ></v-textarea>

            <v-card-actions class="mt-4 px-0">
              <v-spacer></v-spacer>
              <v-btn variant="text" @click="showAddAccountModal = false">Cancel</v-btn>
              <v-btn color="primary" type="submit" :loading="savingAccount" class="font-weight-bold">
                Save Account
              </v-btn>
            </v-card-actions>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/services/api';
import { useNotificationStore } from '@/stores/notification';
import { formatDateTime, formatNumber } from '@/utils/formatters';

const notify = useNotificationStore();

const tab = ref('pnl');
const loading = ref(false);
const loadingPnl = ref(false);

const accounts = ref([]);
const journalEntries = ref([]);
const pnlData = ref(null);
const balanceSheetData = ref(null);
const trialBalanceData = ref(null);
const cashFlowData = ref(null);

const pnlStartDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substring(0, 10));
const pnlEndDate = ref(new Date().toISOString().substring(0, 10));

const selectedTypeFilter = ref('all');

const showManualEntryModal = ref(false);
const showAddAccountModal = ref(false);
const savingJournal = ref(false);
const savingAccount = ref(false);

const journalForm = ref({
  description: '',
  entry_date: new Date().toISOString().substring(0, 10),
  lines: [
    { account_id: null, debit: 0, credit: 0 },
    { account_id: null, debit: 0, credit: 0 },
  ],
});

const accountForm = ref({
  code: '',
  name: '',
  type: 'expense',
  subtype: '',
  opening_balance: 0,
  description: '',
});

const filteredAccounts = computed(() => {
  if (selectedTypeFilter.value === 'all') return accounts.value;
  return accounts.value.filter(a => a.type === selectedTypeFilter.value);
});

const manualEntryTotalDebit = computed(() => {
  return journalForm.value.lines.reduce((sum, l) => sum + Number(l.debit || 0), 0);
});

const manualEntryTotalCredit = computed(() => {
  return journalForm.value.lines.reduce((sum, l) => sum + Number(l.credit || 0), 0);
});

const isManualEntryBalanced = computed(() => {
  return Math.abs(manualEntryTotalDebit.value - manualEntryTotalCredit.value) < 0.01 && manualEntryTotalDebit.value > 0;
});

function getTypeColor(type) {
  switch (type) {
    case 'asset': return 'teal';
    case 'liability': return 'amber-darken-3';
    case 'equity': return 'purple';
    case 'revenue': return 'green';
    case 'expense': return 'deep-orange';
    default: return 'grey';
  }
}

function filterAccountType(type) {
  selectedTypeFilter.value = type;
}

function addJournalLine() {
  journalForm.value.lines.push({ account_id: null, debit: 0, credit: 0 });
}

function removeJournalLine(idx) {
  if (journalForm.value.lines.length > 2) {
    journalForm.value.lines.splice(idx, 1);
  }
}

function openManualEntryModal() {
  journalForm.value = {
    description: '',
    entry_date: new Date().toISOString().substring(0, 10),
    lines: [
      { account_id: accounts.value[0]?.id || null, debit: 0, credit: 0 },
      { account_id: accounts.value[1]?.id || null, debit: 0, credit: 0 },
    ],
  };
  showManualEntryModal.value = true;
}

function onTabChange(newTab) {
  loadActiveTabData();
}

async function loadActiveTabData() {
  loading.value = true;
  if (tab.value === 'pnl') await loadPnl();
  else if (tab.value === 'balance-sheet') await loadBalanceSheet();
  else if (tab.value === 'chart-of-accounts') await loadAccounts();
  else if (tab.value === 'journal-entries') await loadJournalEntries();
  else if (tab.value === 'trial-balance') await loadTrialBalance();
  else if (tab.value === 'cash-flow') await loadCashFlow();
  loading.value = false;
}

async function loadPnl() {
  loadingPnl.value = true;
  try {
    const res = await api.get('/accounting/profit-and-loss', {
      params: {
        start_date: pnlStartDate.value,
        end_date: pnlEndDate.value,
      },
    });
    if (res.data?.success) {
      pnlData.value = res.data.data;
    }
  } catch (err) {
    console.error('Failed to load P&L statement', err);
  } finally {
    loadingPnl.value = false;
  }
}

async function loadBalanceSheet() {
  try {
    const res = await api.get('/accounting/balance-sheet');
    if (res.data?.success) {
      balanceSheetData.value = res.data.data;
    }
  } catch (err) {
    console.error('Failed to load Balance Sheet', err);
  }
}

async function loadAccounts() {
  try {
    const res = await api.get('/accounting/chart-of-accounts');
    if (res.data?.success) {
      accounts.value = res.data.data;
    }
  } catch (err) {
    console.error('Failed to load Chart of Accounts', err);
  }
}

async function loadJournalEntries() {
  try {
    const res = await api.get('/accounting/journal-entries');
    if (res.data?.success) {
      journalEntries.value = res.data.data;
    }
  } catch (err) {
    console.error('Failed to load journal entries', err);
  }
}

async function loadTrialBalance() {
  try {
    const res = await api.get('/accounting/trial-balance');
    if (res.data?.success) {
      trialBalanceData.value = res.data.data;
    }
  } catch (err) {
    console.error('Failed to load Trial Balance', err);
  }
}

async function loadCashFlow() {
  try {
    const res = await api.get('/accounting/cash-flow');
    if (res.data?.success) {
      cashFlowData.value = res.data.data;
    }
  } catch (err) {
    console.error('Failed to load Cash Flow statement', err);
  }
}

async function submitJournalEntry() {
  savingJournal.value = true;
  try {
    const res = await api.post('/accounting/journal-entries', journalForm.value);
    if (res.data?.success) {
      notify.success('Journal entry posted to General Ledger.');
      showManualEntryModal.value = false;
      await Promise.all([loadJournalEntries(), loadAccounts(), loadPnl()]);
    }
  } catch (err) {
    notify.error(err.response?.data?.message || 'Failed to post journal entry');
  } finally {
    savingJournal.value = false;
  }
}

async function submitAccount() {
  savingAccount.value = true;
  try {
    const res = await api.post('/accounting/chart-of-accounts', accountForm.value);
    if (res.data?.success) {
      notify.success('Account added to Chart of Accounts.');
      showAddAccountModal.value = false;
      accountForm.value = { code: '', name: '', type: 'expense', subtype: '', opening_balance: 0, description: '' };
      await loadAccounts();
    }
  } catch (err) {
    notify.error(err.response?.data?.message || 'Failed to create account');
  } finally {
    savingAccount.value = false;
  }
}

onMounted(async () => {
  await Promise.all([loadAccounts(), loadPnl()]);
});
</script>
