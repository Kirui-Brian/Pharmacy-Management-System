<template>
  <div>
    <!-- Standard Header Card -->
    <v-card class="elevation-1 rounded-lg pa-4 mb-4" color="white">
      <div class="d-flex flex-wrap justify-space-between align-center ga-3">
        <div class="d-flex align-center">
          <v-avatar color="purple-darken-2" class="mr-3" size="44">
            <v-icon icon="mdi-chart-box-outline" color="white" size="24"></v-icon>
          </v-avatar>
          <div>
            <h1 class="text-h6 font-weight-bold">Reports & Business Analytics</h1>
            <p class="text-caption text-grey">Sales performance, inventory valuation, expiry risk exposure, and data export</p>
          </div>
        </div>

        <div class="d-flex flex-wrap align-center ga-2">
          <!-- Quick Date Range Selector -->
          <v-btn-toggle
            v-model="datePreset"
            mandatory
            density="compact"
            color="purple-darken-2"
            class="border rounded"
            @update:model-value="applyPreset"
          >
            <v-btn value="today" size="small" class="text-none">Today</v-btn>
            <v-btn value="7days" size="small" class="text-none">7 Days</v-btn>
            <v-btn value="30days" size="small" class="text-none">30 Days</v-btn>
            <v-btn value="this_month" size="small" class="text-none">This Month</v-btn>
            <v-btn value="this_year" size="small" class="text-none">This Year</v-btn>
          </v-btn-toggle>

          <v-btn
            color="purple-darken-2"
            variant="tonal"
            prepend-icon="mdi-printer"
            @click="printReport"
            class="text-none font-weight-medium"
          >
            Print Summary
          </v-btn>

          <v-btn
            icon="mdi-refresh"
            variant="text"
            @click="loadAllReports"
            :loading="loading"
            title="Refresh analytics data"
          ></v-btn>
        </div>
      </div>

      <!-- Custom Date Filter Bar -->
      <v-divider class="my-3"></v-divider>
      <div class="d-flex flex-wrap align-center ga-3">
        <span class="text-caption font-weight-bold text-grey-darken-2">DATE RANGE:</span>
        <div style="max-width: 170px;">
          <v-text-field
            v-model="startDate"
            type="date"
            label="From"
            variant="outlined"
            density="compact"
            hide-details
            @change="datePreset = 'custom'; loadFilteredReports()"
          ></v-text-field>
        </div>
        <div style="max-width: 170px;">
          <v-text-field
            v-model="endDate"
            type="date"
            label="To"
            variant="outlined"
            density="compact"
            hide-details
            @change="datePreset = 'custom'; loadFilteredReports()"
          ></v-text-field>
        </div>
        <v-btn
          color="purple-darken-2"
          variant="flat"
          size="small"
          prepend-icon="mdi-filter"
          @click="loadFilteredReports"
          :loading="loading"
          class="text-none"
        >
          Apply Filter
        </v-btn>
      </div>
    </v-card>

    <!-- Top Executive Metric Cards (Strict 104px uniform height) -->
    <v-row density="compact" class="mb-4">
      <v-col cols="12" sm="6" md="3">
        <v-card
          class="elevation-1 rounded-lg pa-4 border-s-lg border-purple h-100 d-flex flex-column justify-center cursor-default"
          style="min-height: 104px;"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <p class="text-caption text-purple-darken-3 font-weight-bold mb-1">SALES REVENUE (PERIOD)</p>
              <h3 class="text-h6 font-weight-bold text-purple-darken-4">
                KES {{ formatNumber(salesData?.summary?.total_revenue || 0) }}
              </h3>
              <p class="text-caption text-grey">
                {{ salesData?.summary?.total_transactions || 0 }} txns | Avg KES {{ formatNumber(salesData?.summary?.average_basket_size || 0) }}
              </p>
            </div>
            <v-avatar color="purple-lighten-5" size="44">
              <v-icon icon="mdi-cash-register" color="purple-darken-2" size="24"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card
          class="elevation-1 rounded-lg pa-4 border-s-lg border-teal h-100 d-flex flex-column justify-center cursor-default"
          style="min-height: 104px;"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <p class="text-caption text-teal-darken-3 font-weight-bold mb-1">GROSS PROFIT (AFTER COGS)</p>
              <h3 class="text-h6 font-weight-bold text-teal-darken-4">
                KES {{ formatNumber(salesData?.summary?.gross_profit || 0) }}
              </h3>
              <p class="text-caption text-teal-darken-2 font-weight-bold">
                Margin: {{ salesData?.summary?.gross_margin_percent || 0 }}% | COGS KES {{ formatNumber(salesData?.summary?.total_cogs || 0) }}
              </p>
            </div>
            <v-avatar color="teal-lighten-5" size="44">
              <v-icon icon="mdi-trending-up" color="teal-darken-2" size="24"></v-icon>
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
              <p class="text-caption text-blue-darken-3 font-weight-bold mb-1">INVENTORY VALUE (AT COST)</p>
              <h3 class="text-h6 font-weight-bold text-blue-darken-4">
                KES {{ formatNumber(valuationData?.summary?.total_valuation_cost || 0) }}
              </h3>
              <p class="text-caption text-grey">
                Retail: KES {{ formatNumber(valuationData?.summary?.total_valuation_retail || 0) }} (+{{ valuationData?.summary?.overall_markup_percent || 0 }}%)
              </p>
            </div>
            <v-avatar color="blue-lighten-5" size="44">
              <v-icon icon="mdi-warehouse" color="blue-darken-2" size="24"></v-icon>
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
              <p class="text-caption text-deep-orange font-weight-bold mb-1">EXPIRY VALUE-AT-RISK</p>
              <h3 class="text-h6 font-weight-bold text-deep-orange-darken-3">
                KES {{ formatNumber(expiryData?.total_value_at_risk || 0) }}
              </h3>
              <p class="text-caption text-red-darken-1 font-weight-medium">
                {{ (expiryData?.buckets?.expired?.count || 0) + (expiryData?.buckets?.critical_30?.count || 0) }} batches expired / &le;30d
              </p>
            </div>
            <v-avatar color="deep-orange-lighten-5" size="44">
              <v-icon icon="mdi-alert-decagram-outline" color="deep-orange" size="24"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Main Analytics Navigation Tabs -->
    <v-card class="elevation-1 rounded-lg mb-4">
      <v-tabs v-model="activeTab" color="purple-darken-2" bg-color="white" grow>
        <v-tab value="sales" class="text-none font-weight-medium">
          <v-icon icon="mdi-chart-line" start></v-icon>
          Sales & Velocity
        </v-tab>
        <v-tab value="valuation" class="text-none font-weight-medium">
          <v-icon icon="mdi-package-variant-closed" start></v-icon>
          Valuation & Dead Stock
        </v-tab>
        <v-tab value="expiry" class="text-none font-weight-medium">
          <v-icon icon="mdi-calendar-clock" start></v-icon>
          Expiry Risk Exposure
        </v-tab>
        <v-tab value="clinical" class="text-none font-weight-medium">
          <v-icon icon="mdi-stethoscope" start></v-icon>
          Clinical Dispensing
        </v-tab>
        <v-tab value="exports" class="text-none font-weight-medium">
          <v-icon icon="mdi-file-download-outline" start></v-icon>
          Data Export Center
        </v-tab>
      </v-tabs>
    </v-card>

    <!-- ========================================================================= -->
    <!-- TAB 1: SALES TRENDS & PRODUCT VELOCITY -->
    <!-- ========================================================================= -->
    <div v-if="activeTab === 'sales'">
      <!-- Payment Method Breakdown Cards -->
      <v-row density="compact" class="mb-4">
        <v-col cols="12" sm="6" md="3" v-for="(amount, method) in (salesData?.payment_methods || {})" :key="method">
          <v-card class="elevation-1 rounded-lg pa-3 h-100 bg-grey-lighten-5 border">
            <div class="d-flex justify-space-between align-center">
              <div>
                <span class="text-caption text-uppercase font-weight-bold text-grey-darken-2">{{ method }}</span>
                <div class="text-subtitle-1 font-weight-bold text-grey-darken-4">
                  KES {{ formatNumber(amount) }}
                </div>
              </div>
              <v-icon
                :icon="getPaymentIcon(method)"
                :color="getPaymentColor(method)"
                size="28"
              ></v-icon>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <!-- Interactive ApexCharts Visualizations -->
      <v-row density="compact" class="mb-4">
        <!-- Revenue & Profit Trend Area Chart -->
        <v-col cols="12" lg="8">
          <v-card class="elevation-1 rounded-lg pa-4 h-100">
            <div class="d-flex justify-space-between align-center mb-2">
              <div class="d-flex align-center">
                <v-icon icon="mdi-chart-areaspline" color="purple-darken-2" class="mr-2" size="22"></v-icon>
                <h2 class="text-subtitle-1 font-weight-bold">Revenue & Gross Profit Trajectory</h2>
              </div>
              <v-chip size="x-small" color="purple" variant="tonal">Interactive</v-chip>
            </div>
            <div v-if="salesTrendSeries[0]?.data?.length > 0">
              <apexchart key="rep-sales-trend" type="area" height="260" :options="salesTrendChartOptions" :series="salesTrendSeries"></apexchart>
            </div>
            <div v-else class="text-center py-8 text-grey">
              <v-icon icon="mdi-chart-line" size="40" class="mb-2 text-grey-lighten-1"></v-icon>
              <div>No sales trend data recorded for selected period</div>
            </div>
          </v-card>
        </v-col>

        <!-- Payment Method Distribution Donut -->
        <v-col cols="12" lg="4">
          <v-card class="elevation-1 rounded-lg pa-4 h-100">
            <div class="d-flex justify-space-between align-center mb-2">
              <div class="d-flex align-center">
                <v-icon icon="mdi-chart-donut" color="teal-darken-2" class="mr-2" size="22"></v-icon>
                <h2 class="text-subtitle-1 font-weight-bold">Payment Methods</h2>
              </div>
              <v-chip size="x-small" color="teal" variant="tonal">Volume</v-chip>
            </div>
            <div v-if="paymentDonutSeries.some(v => v > 0)">
              <apexchart key="rep-payment-donut" type="donut" height="260" :options="paymentDonutOptions" :series="paymentDonutSeries"></apexchart>
            </div>
            <div v-else class="text-center py-8 text-grey">
              <v-icon icon="mdi-pie-chart" size="40" class="mb-2 text-grey-lighten-1"></v-icon>
              <div>No payment transactions recorded</div>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <v-row density="compact" class="mb-4">
        <!-- Daily Sales Trend Table -->
        <v-col cols="12" lg="7">
          <v-card class="elevation-1 rounded-lg pa-4 h-100">
            <div class="d-flex justify-space-between align-center mb-3">
              <h2 class="text-subtitle-1 font-weight-bold d-flex align-center">
                <v-icon icon="mdi-calendar-range" class="mr-2" color="purple-darken-2" size="20"></v-icon>
                Daily Sales & Margin Breakdown
              </h2>
              <v-chip size="x-small" color="purple" variant="tonal">
                {{ salesData?.daily_trends?.length || 0 }} Days Recorded
              </v-chip>
            </div>

            <v-data-table
              :headers="dailyHeaders"
              :items="salesData?.daily_trends || []"
              :loading="loading"
              density="compact"
              class="elevation-0 border rounded"
              :items-per-page="7"
            >
              <template v-slot:item.date="{ item }">
                <span class="font-weight-medium font-mono">{{ formatDate(item.date) }}</span>
              </template>
              <template v-slot:item.revenue="{ item }">
                <span class="font-weight-bold text-purple-darken-3">KES {{ formatNumber(item.revenue) }}</span>
              </template>
              <template v-slot:item.cogs="{ item }">
                <span class="text-grey-darken-2">KES {{ formatNumber(item.cogs) }}</span>
              </template>
              <template v-slot:item.gross_profit="{ item }">
                <span class="font-weight-bold text-teal-darken-3">KES {{ formatNumber(item.gross_profit) }}</span>
              </template>
              <template v-slot:item.margin="{ item }">
                <v-chip
                  size="x-small"
                  :color="item.revenue > 0 && ((item.gross_profit / item.revenue) >= 0.25) ? 'teal' : 'orange'"
                  variant="flat"
                >
                  {{ item.revenue > 0 ? Math.round((item.gross_profit / item.revenue) * 100) : 0 }}%
                </v-chip>
              </template>
            </v-data-table>
          </v-card>
        </v-col>

        <!-- Top 10 Fast-Selling Products -->
        <v-col cols="12" lg="5">
          <v-card class="elevation-1 rounded-lg pa-4 h-100">
            <div class="d-flex justify-space-between align-center mb-3">
              <h2 class="text-subtitle-1 font-weight-bold d-flex align-center">
                <v-icon icon="mdi-fire" class="mr-2" color="red" size="20"></v-icon>
                Fast-Moving Medicines (Top 10)
              </h2>
              <v-chip size="x-small" color="red" variant="tonal">Velocity</v-chip>
            </div>

            <v-data-table
              :headers="topProductHeaders"
              :items="topProducts"
              :loading="loadingTopProducts"
              density="compact"
              class="elevation-0 border rounded"
              :items-per-page="5"
            >
              <template v-slot:item.name="{ item }">
                <div>
                  <div class="font-weight-bold text-body-2 text-truncate" style="max-width: 170px;">
                    <v-tooltip activator="parent" location="top">{{ item.name }}</v-tooltip>
                    {{ item.name }}
                  </div>
                  <span class="text-caption text-grey">{{ item.sku }}</span>
                </div>
              </template>
              <template v-slot:item.units_sold="{ item }">
                <v-chip size="x-small" color="teal" variant="flat" class="font-weight-bold">
                  {{ item.units_sold }} units
                </v-chip>
              </template>
              <template v-slot:item.revenue="{ item }">
                <span class="font-weight-bold">KES {{ formatNumber(item.revenue) }}</span>
              </template>
            </v-data-table>
          </v-card>
        </v-col>
      </v-row>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: INVENTORY VALUATION & DEAD STOCK -->
    <!-- ========================================================================= -->
    <div v-if="activeTab === 'valuation'">
      <!-- Inventory Valuation by Category Chart -->
      <v-card class="elevation-1 rounded-lg pa-4 mb-4">
        <div class="d-flex justify-space-between align-center mb-2">
          <div class="d-flex align-center">
            <v-icon icon="mdi-chart-bar" color="blue-darken-2" class="mr-2" size="22"></v-icon>
            <h2 class="text-subtitle-1 font-weight-bold">Category Valuation Comparison (Cost vs Retail)</h2>
          </div>
          <v-chip size="x-small" color="blue" variant="tonal">KES Valuation</v-chip>
        </div>
        <div v-if="categoryBarSeries[0]?.data?.length > 0">
          <apexchart key="rep-category-bar" type="bar" height="280" :options="categoryBarOptions" :series="categoryBarSeries"></apexchart>
        </div>
        <div v-else class="text-center py-6 text-grey">
          <v-icon icon="mdi-chart-bar" size="36" class="mb-2 text-grey-lighten-1"></v-icon>
          <div>No category valuation data available</div>
        </div>
      </v-card>

      <!-- Valuation by Category Table -->
      <v-card class="elevation-1 rounded-lg pa-4 mb-4">
        <div class="d-flex justify-space-between align-center mb-3">
          <div>
            <h2 class="text-subtitle-1 font-weight-bold d-flex align-center">
              <v-icon icon="mdi-shape-outline" class="mr-2" color="blue-darken-2" size="20"></v-icon>
              Inventory Valuation Breakdown by Category
            </h2>
            <p class="text-caption text-grey">Valuation of current on-hand batches based on FIFO/FEFO unit purchase and retail prices</p>
          </div>
          <v-chip color="blue" size="small" variant="tonal">
            Total Batches: {{ valuationData?.summary?.total_batches_count || 0 }}
          </v-chip>
        </div>

        <v-data-table
          :headers="categoryValuationHeaders"
          :items="valuationData?.by_category || []"
          :loading="loading"
          density="compact"
          class="elevation-0 border rounded"
          :items-per-page="10"
        >
          <template v-slot:item.category="{ item }">
            <span class="font-weight-bold text-blue-darken-4">{{ item.category }}</span>
          </template>
          <template v-slot:item.batches_count="{ item }">
            <v-chip size="x-small" color="blue-lighten-4" class="text-blue-darken-4">
              {{ item.batches_count }} batches
            </v-chip>
          </template>
          <template v-slot:item.cost_value="{ item }">
            <span class="font-weight-bold">KES {{ formatNumber(item.cost_value) }}</span>
          </template>
          <template v-slot:item.retail_value="{ item }">
            <span class="font-weight-bold text-teal-darken-3">KES {{ formatNumber(item.retail_value) }}</span>
          </template>
          <template v-slot:item.potential_profit="{ item }">
            <span class="font-weight-bold text-purple-darken-3">
              KES {{ formatNumber(item.retail_value - item.cost_value) }}
            </span>
          </template>
          <template v-slot:item.markup="{ item }">
            <v-chip size="x-small" color="teal" variant="flat">
              {{ item.cost_value > 0 ? Math.round(((item.retail_value - item.cost_value) / item.cost_value) * 100) : 0 }}%
            </v-chip>
          </template>
        </v-data-table>
      </v-card>

      <!-- Dead Stock / Stagnant Stock Analysis -->
      <v-card class="elevation-1 rounded-lg pa-4">
        <div class="d-flex flex-wrap justify-space-between align-center mb-3 ga-2">
          <div>
            <h2 class="text-subtitle-1 font-weight-bold d-flex align-center text-red-darken-3">
              <v-icon icon="mdi-archive-cancel-outline" class="mr-2" color="red-darken-3" size="20"></v-icon>
              Dead Stock & Slow-Moving Capital Analyzer
            </h2>
            <p class="text-caption text-grey">Batches with positive stock on shelf but zero dispensing/sales activity in specified period</p>
          </div>

          <div class="d-flex align-center ga-2">
            <span class="text-caption font-weight-bold">INACTIVE FOR:</span>
            <v-select
              v-model="deadStockDays"
              :items="[
                { title: '30 Days', value: 30 },
                { title: '60 Days (Standard)', value: 60 },
                { title: '90 Days (Stagnant)', value: 90 },
                { title: '180 Days (Critical)', value: 180 },
              ]"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details
              style="width: 180px;"
              @update:model-value="loadDeadStock"
            ></v-select>
          </div>
        </div>

        <div class="mb-3 pa-3 bg-red-lighten-5 rounded-lg border border-red-lighten-3 d-flex justify-space-between align-center">
          <div>
            <span class="text-caption text-red-darken-4 font-weight-bold">TIED-UP CAPITAL IN DEAD STOCK:</span>
            <span class="text-h6 font-weight-bold text-red-darken-4 ml-2">
              KES {{ formatNumber(deadStockData?.total_tied_up_capital || 0) }}
            </span>
          </div>
          <v-chip color="red" size="small" variant="flat">
            {{ deadStockData?.total_items_count || 0 }} Stagnant Batches
          </v-chip>
        </div>

        <v-data-table
          :headers="deadStockHeaders"
          :items="deadStockData?.items || []"
          :loading="loadingDeadStock"
          density="compact"
          class="elevation-0 border rounded"
          :items-per-page="8"
        >
          <template v-slot:item.product_name="{ item }">
            <div>
              <div class="font-weight-bold text-body-2 text-truncate" style="max-width: 220px;">
                <v-tooltip activator="parent" location="top">{{ item.product_name }}</v-tooltip>
                {{ item.product_name }}
              </div>
              <span class="text-caption text-grey">{{ item.sku }}</span>
            </div>
          </template>
          <template v-slot:item.quantity_remaining="{ item }">
            <span class="font-weight-bold">{{ item.quantity_remaining }}</span>
          </template>
          <template v-slot:item.unit_cost="{ item }">
            <span>KES {{ formatNumber(item.unit_cost) }}</span>
          </template>
          <template v-slot:item.tied_up_capital="{ item }">
            <span class="font-weight-bold text-red-darken-3">KES {{ formatNumber(item.tied_up_capital) }}</span>
          </template>
          <template v-slot:item.expiry_date="{ item }">
            <span class="font-mono text-caption">{{ formatDate(item.expiry_date) }}</span>
          </template>
        </v-data-table>
      </v-card>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 3: EXPIRY RISK & VALUE-AT-RISK -->
    <!-- ========================================================================= -->
    <div v-if="activeTab === 'expiry'">
      <!-- 4 Risk Buckets Cards -->
      <v-row density="compact" class="mb-4">
        <v-col cols="12" sm="6" md="3">
          <v-card class="elevation-1 rounded-lg pa-3 border-s-lg border-red-darken-4 bg-red-lighten-5 h-100">
            <div class="d-flex justify-space-between align-center">
              <div>
                <span class="text-caption font-weight-bold text-red-darken-4">EXPIRED (BLOCKED)</span>
                <div class="text-h6 font-weight-bold text-red-darken-4">
                  KES {{ formatNumber(expiryData?.buckets?.expired?.cost_value || 0) }}
                </div>
                <span class="text-caption text-red-darken-3 font-weight-medium">
                  {{ expiryData?.buckets?.expired?.count || 0 }} batches ({{ expiryData?.buckets?.expired?.units || 0 }} units)
                </span>
              </div>
              <v-icon icon="mdi-cancel" color="red-darken-4" size="32"></v-icon>
            </div>
          </v-card>
        </v-col>

        <v-col cols="12" sm="6" md="3">
          <v-card class="elevation-1 rounded-lg pa-3 border-s-lg border-red bg-red-lighten-5 h-100">
            <div class="d-flex justify-space-between align-center">
              <div>
                <span class="text-caption font-weight-bold text-red-darken-3">CRITICAL (&le; 30 DAYS)</span>
                <div class="text-h6 font-weight-bold text-red-darken-3">
                  KES {{ formatNumber(expiryData?.buckets?.critical_30?.cost_value || 0) }}
                </div>
                <span class="text-caption text-red-darken-2 font-weight-medium">
                  {{ expiryData?.buckets?.critical_30?.count || 0 }} batches ({{ expiryData?.buckets?.critical_30?.units || 0 }} units)
                </span>
              </div>
              <v-icon icon="mdi-alert-circle" color="red" size="32"></v-icon>
            </div>
          </v-card>
        </v-col>

        <v-col cols="12" sm="6" md="3">
          <v-card class="elevation-1 rounded-lg pa-3 border-s-lg border-orange bg-orange-lighten-5 h-100">
            <div class="d-flex justify-space-between align-center">
              <div>
                <span class="text-caption font-weight-bold text-orange-darken-3">WARNING (31–60 DAYS)</span>
                <div class="text-h6 font-weight-bold text-orange-darken-3">
                  KES {{ formatNumber(expiryData?.buckets?.warning_60?.cost_value || 0) }}
                </div>
                <span class="text-caption text-orange-darken-2 font-weight-medium">
                  {{ expiryData?.buckets?.warning_60?.count || 0 }} batches ({{ expiryData?.buckets?.warning_60?.units || 0 }} units)
                </span>
              </div>
              <v-icon icon="mdi-alert" color="orange" size="32"></v-icon>
            </div>
          </v-card>
        </v-col>

        <v-col cols="12" sm="6" md="3">
          <v-card class="elevation-1 rounded-lg pa-3 border-s-lg border-amber-darken-2 bg-amber-lighten-5 h-100">
            <div class="d-flex justify-space-between align-center">
              <div>
                <span class="text-caption font-weight-bold text-amber-darken-4">CAUTION (61–90 DAYS)</span>
                <div class="text-h6 font-weight-bold text-amber-darken-4">
                  KES {{ formatNumber(expiryData?.buckets?.caution_90?.cost_value || 0) }}
                </div>
                <span class="text-caption text-amber-darken-3 font-weight-medium">
                  {{ expiryData?.buckets?.caution_90?.count || 0 }} batches ({{ expiryData?.buckets?.caution_90?.units || 0 }} units)
                </span>
              </div>
              <v-icon icon="mdi-clock-alert-outline" color="amber-darken-3" size="32"></v-icon>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <!-- Expiry Risk Distribution Chart -->
      <v-card class="elevation-1 rounded-lg pa-4 mb-4">
        <div class="d-flex justify-space-between align-center mb-2">
          <div class="d-flex align-center">
            <v-icon icon="mdi-chart-donut" color="deep-orange" class="mr-2" size="22"></v-icon>
            <h2 class="text-subtitle-1 font-weight-bold">Capital Exposure by Expiry Stage</h2>
          </div>
          <v-chip size="x-small" color="deep-orange" variant="tonal">Risk Distribution</v-chip>
        </div>
        <div v-if="expiryDonutSeries.some(v => v > 0)">
          <apexchart key="rep-expiry-donut" type="donut" height="260" :options="expiryDonutOptions" :series="expiryDonutSeries"></apexchart>
        </div>
        <div v-else class="text-center py-6 text-grey">
          <v-icon icon="mdi-shield-check" size="36" color="teal" class="mb-2"></v-icon>
          <div class="font-weight-medium text-teal-darken-3">No critical expiry exposure detected</div>
        </div>
      </v-card>

      <!-- Detailed Expiry Watchlist Table -->
      <v-card class="elevation-1 rounded-lg pa-4">
        <div class="d-flex justify-space-between align-center mb-3">
          <div>
            <h2 class="text-subtitle-1 font-weight-bold d-flex align-center">
              <v-icon icon="mdi-format-list-checks" class="mr-2" color="deep-orange" size="20"></v-icon>
              High-Risk Expiry Watchlist (&le; 90 Days)
            </h2>
            <p class="text-caption text-grey">Batches needing price markdown, supplier return, or aggressive FIFO dispensing</p>
          </div>
          <v-btn
            color="deep-orange"
            variant="outlined"
            size="small"
            prepend-icon="mdi-download"
            @click="downloadCsv('expiry')"
            :loading="exportingExpiry"
            class="text-none"
          >
            Export Watchlist CSV
          </v-btn>
        </div>

        <v-data-table
          :headers="expiryHeaders"
          :items="allExpiringBatches"
          :loading="loading"
          density="compact"
          class="elevation-0 border rounded"
          :items-per-page="10"
        >
          <template v-slot:item.product_name="{ item }">
            <div>
              <div class="font-weight-bold text-body-2 text-truncate" style="max-width: 200px;">
                <v-tooltip activator="parent" location="top">{{ item.product_name }}</v-tooltip>
                {{ item.product_name }}
              </div>
              <span class="text-caption text-grey">{{ item.sku }}</span>
            </div>
          </template>
          <template v-slot:item.batch_number="{ item }">
            <span class="font-mono text-caption font-weight-bold">{{ item.batch_number }}</span>
          </template>
          <template v-slot:item.quantity_remaining="{ item }">
            <span class="font-weight-bold">{{ item.quantity_remaining }}</span>
          </template>
          <template v-slot:item.expiry_date="{ item }">
            <span class="font-mono text-caption">{{ formatDate(item.expiry_date) }}</span>
          </template>
          <template v-slot:item.days_left="{ item }">
            <v-chip
              size="x-small"
              :color="getExpiryRiskColor(item.days_left)"
              variant="flat"
              class="font-weight-bold"
            >
              {{ item.days_left < 0 ? 'EXPIRED' : `${item.days_left}d remaining` }}
            </v-chip>
          </template>
          <template v-slot:item.cost_value="{ item }">
            <span class="font-weight-bold text-deep-orange-darken-3">
              KES {{ formatNumber(item.cost_value) }}
            </span>
          </template>
        </v-data-table>
      </v-card>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 4: CLINICAL DISPENSING & DOCTOR METRICS -->
    <!-- ========================================================================= -->
    <div v-if="activeTab === 'clinical'">
      <!-- Clinical Summary KPI Cards -->
      <v-row density="compact" class="mb-4">
        <v-col cols="12" sm="6" md="3">
          <v-card class="elevation-1 rounded-lg pa-3 border-s-lg border-indigo h-100">
            <div class="d-flex justify-space-between align-center">
              <div>
                <span class="text-caption text-indigo font-weight-bold">PRESCRIPTIONS RECEIVED</span>
                <div class="text-h6 font-weight-bold text-indigo-darken-4">
                  {{ clinicalData?.summary?.total_prescriptions || 0 }}
                </div>
                <span class="text-caption text-grey">In active period</span>
              </div>
              <v-icon icon="mdi-file-document-outline" color="indigo" size="30"></v-icon>
            </div>
          </v-card>
        </v-col>

        <v-col cols="12" sm="6" md="3">
          <v-card class="elevation-1 rounded-lg pa-3 border-s-lg border-teal h-100">
            <div class="d-flex justify-space-between align-center">
              <div>
                <span class="text-caption text-teal font-weight-bold">FULFILLMENT RATE</span>
                <div class="text-h6 font-weight-bold text-teal-darken-4">
                  {{ clinicalData?.summary?.fulfillment_rate_percent || 0 }}%
                </div>
                <span class="text-caption text-teal-darken-2 font-weight-medium">
                  {{ clinicalData?.summary?.fully_dispensed || 0 }} fully completed
                </span>
              </div>
              <v-icon icon="mdi-check-decagram" color="teal" size="30"></v-icon>
            </div>
          </v-card>
        </v-col>

        <v-col cols="12" sm="6" md="3">
          <v-card class="elevation-1 rounded-lg pa-3 border-s-lg border-amber-darken-3 h-100">
            <div class="d-flex justify-space-between align-center">
              <div>
                <span class="text-caption text-amber-darken-4 font-weight-bold">PARTIAL / PENDING</span>
                <div class="text-h6 font-weight-bold text-amber-darken-4">
                  {{ (clinicalData?.summary?.partially_dispensed || 0) + (clinicalData?.summary?.pending || 0) }}
                </div>
                <span class="text-caption text-grey">Awaiting stock / patient</span>
              </div>
              <v-icon icon="mdi-clock-outline" color="amber-darken-3" size="30"></v-icon>
            </div>
          </v-card>
        </v-col>

        <v-col cols="12" sm="6" md="3">
          <v-card class="elevation-1 rounded-lg pa-3 border-s-lg border-cyan-darken-2 h-100">
            <div class="d-flex justify-space-between align-center">
              <div>
                <span class="text-caption text-cyan-darken-4 font-weight-bold">DISPENSING EVENTS</span>
                <div class="text-h6 font-weight-bold text-cyan-darken-4">
                  {{ clinicalData?.summary?.dispensation_events || 0 }}
                </div>
                <span class="text-caption text-grey">Patient counselling logs</span>
              </div>
              <v-icon icon="mdi-hand-heart" color="cyan-darken-2" size="30"></v-icon>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <!-- Prescribers & Clinics Table -->
      <v-card class="elevation-1 rounded-lg pa-4">
        <div class="d-flex justify-space-between align-center mb-3">
          <div>
            <h2 class="text-subtitle-1 font-weight-bold d-flex align-center">
              <v-icon icon="mdi-doctor" class="mr-2" color="indigo" size="20"></v-icon>
              Top Referring Doctors & Clinics
            </h2>
            <p class="text-caption text-grey">Prescribing clinicians driving patient flow and prescription volume</p>
          </div>
        </div>

        <v-data-table
          :headers="prescriberHeaders"
          :items="clinicalData?.top_prescribers || []"
          :loading="loading"
          density="compact"
          class="elevation-0 border rounded"
          :items-per-page="5"
        >
          <template v-slot:item.prescriber_name="{ item }">
            <span class="font-weight-bold text-indigo-darken-3">{{ item.prescriber_name || 'Walk-in / External' }}</span>
          </template>
          <template v-slot:item.prescriber_facility="{ item }">
            <span>{{ item.prescriber_facility || 'Private Clinic / General' }}</span>
          </template>
          <template v-slot:item.count="{ item }">
            <v-chip size="x-small" color="indigo" variant="flat" class="font-weight-bold">
              {{ item.count }} Rx written
            </v-chip>
          </template>
        </v-data-table>
      </v-card>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 5: DATA EXPORT CENTER -->
    <!-- ========================================================================= -->
    <div v-if="activeTab === 'exports'">
      <v-row density="compact">
        <!-- Sales CSV Export Card -->
        <v-col cols="12" md="4">
          <v-card class="elevation-1 rounded-lg pa-4 h-100 border-t-lg border-purple d-flex flex-column justify-space-between">
            <div>
              <div class="d-flex align-center mb-2">
                <v-avatar color="purple-lighten-5" class="mr-2" size="36">
                  <v-icon icon="mdi-file-excel-box" color="purple-darken-2" size="22"></v-icon>
                </v-avatar>
                <h3 class="text-subtitle-1 font-weight-bold">Sales & Receipts Report</h3>
              </div>
              <p class="text-caption text-grey mb-4">
                Detailed transaction audit log including receipt number, timestamp (ISO + local), cashier, customer, subtotal, tax, discount, payment methods, and status.
              </p>
            </div>
            <div>
              <v-btn
                block
                color="purple-darken-2"
                prepend-icon="mdi-download"
                @click="downloadCsv('sales')"
                :loading="exportingSales"
                class="text-none font-weight-medium"
              >
                Download Sales CSV
              </v-btn>
            </div>
          </v-card>
        </v-col>

        <!-- Inventory Valuation CSV Export Card -->
        <v-col cols="12" md="4">
          <v-card class="elevation-1 rounded-lg pa-4 h-100 border-t-lg border-blue d-flex flex-column justify-space-between">
            <div>
              <div class="d-flex align-center mb-2">
                <v-avatar color="blue-lighten-5" class="mr-2" size="36">
                  <v-icon icon="mdi-file-excel-box" color="blue-darken-2" size="22"></v-icon>
                </v-avatar>
                <h3 class="text-subtitle-1 font-weight-bold">Inventory Valuation Master</h3>
              </div>
              <p class="text-caption text-grey mb-4">
                Complete batch-level valuation spreadsheet with SKU, batch number, remaining stock, unit purchase price, unit retail price, total valuation cost, and profit margins.
              </p>
            </div>
            <div>
              <v-btn
                block
                color="blue-darken-2"
                prepend-icon="mdi-download"
                @click="downloadCsv('inventory')"
                :loading="exportingInventory"
                class="text-none font-weight-medium"
              >
                Download Valuation CSV
              </v-btn>
            </div>
          </v-card>
        </v-col>

        <!-- Expiry Watchlist CSV Export Card -->
        <v-col cols="12" md="4">
          <v-card class="elevation-1 rounded-lg pa-4 h-100 border-t-lg border-deep-orange d-flex flex-column justify-space-between">
            <div>
              <div class="d-flex align-center mb-2">
                <v-avatar color="deep-orange-lighten-5" class="mr-2" size="36">
                  <v-icon icon="mdi-file-excel-box" color="deep-orange" size="22"></v-icon>
                </v-avatar>
                <h3 class="text-subtitle-1 font-weight-bold">Expiry & Value-at-Risk</h3>
              </div>
              <p class="text-caption text-grey mb-4">
                Full compliance watchlist highlighting expired batches and imminent expirations across 30, 60, and 90 day intervals with capital tied-up and risk categorizations.
              </p>
            </div>
            <div>
              <v-btn
                block
                color="deep-orange"
                prepend-icon="mdi-download"
                @click="downloadCsv('expiry')"
                :loading="exportingExpiry"
                class="text-none font-weight-medium"
              >
                Download Expiry CSV
              </v-btn>
            </div>
          </v-card>
        </v-col>
      </v-row>
    </div>

    <!-- Hidden Printable Area for window.print() -->
    <div id="print-report-summary" class="d-none">
      <div style="font-family: sans-serif; padding: 20px;">
        <h1 style="color: #4A148C; margin-bottom: 4px;">Afya Meds Pharmacy — Executive Analytics Report</h1>
        <p style="color: #666; font-size: 12px; margin-bottom: 20px;">
          Generated on {{ currentFormattedTime }} | Filter Period: {{ formatDate(startDate) }} to {{ formatDate(endDate) }}
        </p>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;" border="1">
          <tr style="background-color: #EDE7F6;">
            <th style="padding: 8px; text-align: left;">Metric</th>
            <th style="padding: 8px; text-align: right;">Value (KES)</th>
          </tr>
          <tr>
            <td style="padding: 8px;">Total Period Sales Revenue</td>
            <td style="padding: 8px; text-align: right; font-weight: bold;">KES {{ formatNumber(salesData?.summary?.total_revenue || 0) }}</td>
          </tr>
          <tr>
            <td style="padding: 8px;">Cost of Goods Sold (COGS)</td>
            <td style="padding: 8px; text-align: right;">KES {{ formatNumber(salesData?.summary?.total_cogs || 0) }}</td>
          </tr>
          <tr>
            <td style="padding: 8px;">Gross Profit</td>
            <td style="padding: 8px; text-align: right; font-weight: bold;">KES {{ formatNumber(salesData?.summary?.gross_profit || 0) }} ({{ salesData?.summary?.gross_margin_percent || 0 }}%)</td>
          </tr>
          <tr>
            <td style="padding: 8px;">Total Inventory Value (at Cost)</td>
            <td style="padding: 8px; text-align: right; font-weight: bold;">KES {{ formatNumber(valuationData?.summary?.total_valuation_cost || 0) }}</td>
          </tr>
          <tr>
            <td style="padding: 8px;">Expiry Value-at-Risk (&le; 90 Days)</td>
            <td style="padding: 8px; text-align: right; color: #D84315; font-weight: bold;">KES {{ formatNumber(expiryData?.total_value_at_risk || 0) }}</td>
          </tr>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/services/api';
import { useNotificationStore } from '@/stores/notification';
import { formatDateTime, formatDate, formatNumber } from '@/utils/formatters';

const notify = useNotificationStore();

const activeTab = ref('sales');
const datePreset = ref('30days');
const loading = ref(false);
const loadingTopProducts = ref(false);
const loadingDeadStock = ref(false);

const exportingSales = ref(false);
const exportingInventory = ref(false);
const exportingExpiry = ref(false);

const deadStockDays = ref(60);

// Dates formatted YYYY-MM-DD for native input[type="date"]
const now = new Date();
const pad = (n) => String(n).padStart(2, '0');
const toInputDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

const startDate = ref(toInputDate(new Date(Date.now() - 30 * 24 * 60 * 60 * 1000)));
const endDate = ref(toInputDate(now));

const currentFormattedTime = computed(() => {
  return formatDateTime(new Date());
});

// Data Containers
const salesData = ref(null);
const topProducts = ref([]);
const valuationData = ref(null);
const expiryData = ref(null);
const deadStockData = ref(null);
const clinicalData = ref(null);

// Table Headers
const dailyHeaders = [
  { title: 'Date', key: 'date', sortable: true },
  { title: 'Transactions', key: 'transactions', align: 'center', sortable: true },
  { title: 'Revenue', key: 'revenue', align: 'end', sortable: true },
  { title: 'COGS', key: 'cogs', align: 'end', sortable: true },
  { title: 'Gross Profit', key: 'gross_profit', align: 'end', sortable: true },
  { title: 'Margin', key: 'margin', align: 'center' },
];

const topProductHeaders = [
  { title: 'Medicine Name', key: 'name' },
  { title: 'Units', key: 'units_sold', align: 'center', sortable: true },
  { title: 'Revenue', key: 'revenue', align: 'end', sortable: true },
];

const categoryValuationHeaders = [
  { title: 'Category Name', key: 'category', sortable: true },
  { title: 'Batches', key: 'batches_count', align: 'center', sortable: true },
  { title: 'Cost Value (KES)', key: 'cost_value', align: 'end', sortable: true },
  { title: 'Retail Value (KES)', key: 'retail_value', align: 'end', sortable: true },
  { title: 'Potential Profit (KES)', key: 'potential_profit', align: 'end', sortable: true },
  { title: 'Markup', key: 'markup', align: 'center' },
];

const deadStockHeaders = [
  { title: 'Medicine & SKU', key: 'product_name' },
  { title: 'Batch #', key: 'batch_number' },
  { title: 'Supplier', key: 'supplier' },
  { title: 'Stock Left', key: 'quantity_remaining', align: 'center', sortable: true },
  { title: 'Unit Cost', key: 'unit_cost', align: 'end' },
  { title: 'Tied Capital', key: 'tied_up_capital', align: 'end', sortable: true },
  { title: 'Expiry Date', key: 'expiry_date', sortable: true },
];

const expiryHeaders = [
  { title: 'Medicine & SKU', key: 'product_name' },
  { title: 'Batch #', key: 'batch_number' },
  { title: 'Stock Remaining', key: 'quantity_remaining', align: 'center', sortable: true },
  { title: 'Expiry Date', key: 'expiry_date', sortable: true },
  { title: 'Status & Urgency', key: 'days_left', align: 'center', sortable: true },
  { title: 'Value-at-Risk', key: 'cost_value', align: 'end', sortable: true },
];

const prescriberHeaders = [
  { title: 'Prescriber / Doctor', key: 'prescriber_name', sortable: true },
  { title: 'Clinic / Facility', key: 'prescriber_facility', sortable: true },
  { title: 'Prescriptions Completed', key: 'count', align: 'center', sortable: true },
];

// Computed combined expiring items for watchlist
const allExpiringBatches = computed(() => {
  if (!expiryData.value?.buckets) return [];
  const b = expiryData.value.buckets;
  return [
    ...(b.expired?.items || []),
    ...(b.critical_30?.items || []),
    ...(b.warning_60?.items || []),
    ...(b.caution_90?.items || []),
  ];
});

// Presets handler
function applyPreset(preset) {
  const today = new Date();
  if (preset === 'today') {
    startDate.value = toInputDate(today);
    endDate.value = toInputDate(today);
  } else if (preset === '7days') {
    startDate.value = toInputDate(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000));
    endDate.value = toInputDate(today);
  } else if (preset === '30days') {
    startDate.value = toInputDate(new Date(Date.now() - 30 * 24 * 60 * 60 * 1000));
    endDate.value = toInputDate(today);
  } else if (preset === 'this_month') {
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    startDate.value = toInputDate(firstDay);
    endDate.value = toInputDate(today);
  } else if (preset === 'this_year') {
    const firstDayOfYear = new Date(today.getFullYear(), 0, 1);
    startDate.value = toInputDate(firstDayOfYear);
    endDate.value = toInputDate(today);
  }
  loadFilteredReports();
}

// =========================================================================
// ApexCharts Configuration & Series
// =========================================================================

// Tab 1: Daily Revenue & Gross Profit Area Chart
const salesTrendSeries = computed(() => {
  const trends = salesData.value?.daily_trends || [];
  return [
    {
      name: 'Sales Revenue (KES)',
      data: trends.map((t) => Math.round(t.revenue || 0)),
    },
    {
      name: 'Gross Profit (KES)',
      data: trends.map((t) => Math.round(t.gross_profit || 0)),
    },
  ];
});

const salesTrendChartOptions = computed(() => ({
  chart: {
    type: 'area',
    toolbar: { show: false },
    fontFamily: 'inherit',
  },
  colors: ['#7B1FA2', '#00897B'],
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  xaxis: {
    categories: (salesData.value?.daily_trends || []).map((t) => t.date),
    labels: { rotate: -25, style: { fontSize: '11px' } },
  },
  yaxis: {
    labels: {
      formatter: (val) => 'KES ' + Number(val).toLocaleString(),
    },
  },
  tooltip: {
    y: {
      formatter: (val) => 'KES ' + Number(val).toLocaleString(),
    },
  },
  legend: { position: 'top', horizontalAlign: 'right' },
}));

// Tab 1: Payment Method Distribution Donut
const paymentDonutSeries = computed(() => {
  const methods = salesData.value?.payment_methods || {};
  return [
    Number(methods.cash || 0),
    Number(methods.mpesa || 0),
    Number(methods.card || 0),
    Number(methods.credit || 0),
  ];
});

const paymentDonutOptions = computed(() => ({
  chart: {
    type: 'donut',
    fontFamily: 'inherit',
  },
  labels: ['Cash', 'M-Pesa', 'Card', 'Credit Account'],
  colors: ['#00897B', '#2E7D32', '#1E88E5', '#FB8C00'],
  legend: { position: 'bottom' },
  tooltip: {
    y: {
      formatter: (val) => 'KES ' + Number(val).toLocaleString(),
    },
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => Math.round(val) + '%',
  },
}));

// Tab 2: Category Valuation Bar Chart
const categoryBarSeries = computed(() => {
  const categories = valuationData.value?.by_category || [];
  return [
    {
      name: 'Cost Valuation (KES)',
      data: categories.map((c) => Math.round(c.cost_value || 0)),
    },
    {
      name: 'Retail Valuation (KES)',
      data: categories.map((c) => Math.round(c.retail_value || 0)),
    },
  ];
});

const categoryBarOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'inherit',
  },
  plotOptions: {
    bar: {
      horizontal: false,
      columnWidth: '55%',
      borderRadius: 4,
    },
  },
  colors: ['#1976D2', '#00897B'],
  dataLabels: { enabled: false },
  stroke: { show: true, width: 2, colors: ['transparent'] },
  xaxis: {
    categories: (valuationData.value?.by_category || []).map((c) => c.category),
    labels: { rotate: -20, style: { fontSize: '11px' } },
  },
  yaxis: {
    labels: {
      formatter: (val) => 'KES ' + Number(val).toLocaleString(),
    },
  },
  tooltip: {
    y: {
      formatter: (val) => 'KES ' + Number(val).toLocaleString(),
    },
  },
  legend: { position: 'top', horizontalAlign: 'right' },
}));

// Tab 3: Expiry Risk Distribution Donut
const expiryDonutSeries = computed(() => {
  const b = expiryData.value?.buckets;
  if (!b) return [0, 0, 0, 0];
  return [
    Number(b.expired?.cost_value || 0),
    Number(b.critical_30?.cost_value || 0),
    Number(b.warning_60?.cost_value || 0),
    Number(b.caution_90?.cost_value || 0),
  ];
});

const expiryDonutOptions = computed(() => ({
  chart: {
    type: 'donut',
    fontFamily: 'inherit',
  },
  labels: ['Expired', 'Critical (≤30d)', 'Warning (31–60d)', 'Caution (61–90d)'],
  colors: ['#B71C1C', '#E53935', '#FB8C00', '#FFB300'],
  legend: { position: 'bottom' },
  tooltip: {
    y: {
      formatter: (val) => 'KES ' + Number(val).toLocaleString(),
    },
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => Math.round(val) + '%',
  },
}));

function getPaymentIcon(method) {
  const icons = {
    cash: 'mdi-cash',
    mpesa: 'mdi-cellphone-check',
    card: 'mdi-credit-card-outline',
    credit: 'mdi-account-cash-outline',
  };
  return icons[method?.toLowerCase()] || 'mdi-currency-usd';
}

function getPaymentColor(method) {
  const colors = {
    cash: 'teal-darken-2',
    mpesa: 'green-darken-2',
    card: 'indigo-darken-2',
    credit: 'orange-darken-2',
  };
  return colors[method?.toLowerCase()] || 'grey-darken-2';
}

function getExpiryRiskColor(daysLeft) {
  if (daysLeft < 0) return 'red-darken-4';
  if (daysLeft <= 30) return 'red';
  if (daysLeft <= 60) return 'orange-darken-2';
  return 'amber-darken-3';
}

// Data loaders
async function loadAllReports() {
  loading.value = true;
  try {
    await Promise.all([
      loadSalesAnalytics(),
      loadTopProducts(),
      loadValuation(),
      loadExpiryRisk(),
      loadDeadStock(),
      loadClinical(),
    ]);
  } catch (err) {
    notify.error('Failed to load some analytics reports. Please try again.');
  } finally {
    loading.value = false;
  }
}

async function loadFilteredReports() {
  loading.value = true;
  try {
    await Promise.all([
      loadSalesAnalytics(),
      loadTopProducts(),
      loadClinical(),
    ]);
  } catch (err) {
    notify.error('Failed to update filtered reports.');
  } finally {
    loading.value = false;
  }
}

async function loadSalesAnalytics() {
  const res = await api.get('/reports/sales-analytics', {
    params: { start_date: startDate.value, end_date: endDate.value }
  });
  if (res.data?.success) {
    salesData.value = res.data.data;
  }
}

async function loadTopProducts() {
  loadingTopProducts.value = true;
  try {
    const res = await api.get('/reports/top-selling-products', {
      params: { start_date: startDate.value, end_date: endDate.value, limit: 10 }
    });
    if (res.data?.success) {
      topProducts.value = res.data.data;
    }
  } finally {
    loadingTopProducts.value = false;
  }
}

async function loadValuation() {
  const res = await api.get('/reports/inventory-valuation');
  if (res.data?.success) {
    valuationData.value = res.data.data;
  }
}

async function loadExpiryRisk() {
  const res = await api.get('/reports/expiry-risk-analysis');
  if (res.data?.success) {
    expiryData.value = res.data.data;
  }
}

async function loadDeadStock() {
  loadingDeadStock.value = true;
  try {
    const res = await api.get('/reports/dead-stock', {
      params: { days: deadStockDays.value }
    });
    if (res.data?.success) {
      deadStockData.value = res.data.data;
    }
  } finally {
    loadingDeadStock.value = false;
  }
}

async function loadClinical() {
  const res = await api.get('/reports/clinical-dispensing', {
    params: { start_date: startDate.value, end_date: endDate.value }
  });
  if (res.data?.success) {
    clinicalData.value = res.data.data;
  }
}

// CSV Streamed Exporter
async function downloadCsv(type) {
  let url = '';
  let filename = '';
  let params = {};

  if (type === 'sales') {
    exportingSales.value = true;
    url = '/reports/export/sales-csv';
    filename = `sales_report_${startDate.value}_to_${endDate.value}.csv`;
    params = { start_date: startDate.value, end_date: endDate.value };
  } else if (type === 'inventory') {
    exportingInventory.value = true;
    url = '/reports/export/inventory-csv';
    filename = `inventory_valuation_${toInputDate(new Date())}.csv`;
  } else if (type === 'expiry') {
    exportingExpiry.value = true;
    url = '/reports/export/expiry-csv';
    filename = `expiry_watchlist_${toInputDate(new Date())}.csv`;
  }

  try {
    const response = await api.get(url, {
      responseType: 'blob',
      params,
    });

    const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' });
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(downloadUrl);

    notify.success(`${filename} generated and downloaded successfully!`);
  } catch (err) {
    notify.error('Failed to export CSV. Please try again.');
  } finally {
    if (type === 'sales') exportingSales.value = false;
    if (type === 'inventory') exportingInventory.value = false;
    if (type === 'expiry') exportingExpiry.value = false;
  }
}

function printReport() {
  window.print();
}

onMounted(() => {
  loadAllReports();
});
</script>

<style scoped>
@media print {
  body * {
    visibility: hidden;
  }
  #print-report-summary, #print-report-summary * {
    visibility: visible !important;
  }
  #print-report-summary {
    display: block !important;
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
}
</style>
