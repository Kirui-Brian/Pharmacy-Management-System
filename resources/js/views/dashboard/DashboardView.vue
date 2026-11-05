<template>
  <div>
    <!-- Standard Header Card -->
    <v-card class="elevation-1 rounded-lg pa-4 mb-4" color="white">
      <div class="d-flex flex-wrap justify-space-between align-center ga-3">
        <div class="d-flex align-center">
          <v-avatar color="teal-darken-2" class="mr-3" size="44">
            <v-icon icon="mdi-view-dashboard-outline" color="white" size="24"></v-icon>
          </v-avatar>
          <div>
            <h1 class="text-h6 font-weight-bold">Pharmacy Operational Dashboard</h1>
            <p class="text-caption text-grey">
              Operating as <span class="font-weight-bold text-teal-darken-3">{{ authStore.user?.name }}</span> at
              <span class="font-weight-bold">{{ authStore.branch?.name || 'Main Branch' }}</span>
            </p>
          </div>
        </div>

        <div class="d-flex ga-2">
          <v-btn
            v-if="authStore.hasPermission('pos.access')"
            color="primary"
            prepend-icon="mdi-cash-register"
            class="font-weight-medium text-none"
            to="/pos"
          >
            Open POS Counter
          </v-btn>
        </div>
      </div>
    </v-card>

    <!-- Quick Status Cards (Uniform height & structure) -->
    <v-row class="mb-4" density="compact">
      <v-col cols="12" sm="6" md="3">
        <v-card
          class="elevation-1 rounded-lg pa-4 border-s-lg border-teal h-100 d-flex flex-column justify-center cursor-default"
          style="min-height: 104px;"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption font-weight-bold text-grey">SYSTEM STATUS</div>
              <div class="text-h6 font-weight-bold text-teal-darken-2">ONLINE (v1.0)</div>
              <div class="text-caption text-success font-weight-medium">MySQL & Laravel Active</div>
            </div>
            <v-avatar color="teal-lighten-5" size="44">
              <v-icon icon="mdi-server" color="teal" size="24"></v-icon>
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
              <div class="text-caption font-weight-bold text-grey">ACTIVE BRANCH</div>
              <div class="text-h6 font-weight-bold text-blue-darken-2">{{ authStore.branch?.code || 'KUR-01' }}</div>
              <div class="text-caption text-grey text-truncate" style="max-width: 170px;">{{ authStore.branch?.name || 'Kuresoi' }}</div>
            </div>
            <v-avatar color="blue-lighten-5" size="44">
              <v-icon icon="mdi-storefront" color="blue" size="24"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card
          class="elevation-1 rounded-lg pa-4 border-s-lg border-amber h-100 d-flex flex-column justify-center cursor-default"
          style="min-height: 104px;"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption font-weight-bold text-grey">YOUR ROLE</div>
              <div class="text-h6 font-weight-bold text-amber-darken-3 text-capitalize">
                {{ authStore.roles[0] || 'Staff' }}
              </div>
              <div class="text-caption text-grey">{{ authStore.permissions.length }} Active Permissions</div>
            </div>
            <v-avatar color="amber-lighten-5" size="44">
              <v-icon icon="mdi-shield-check" color="amber-darken-2" size="24"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card
          class="elevation-1 rounded-lg pa-4 border-s-lg border-purple h-100 d-flex flex-column justify-center cursor-default"
          style="min-height: 104px;"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption font-weight-bold text-grey">DEFAULT CURRENCY</div>
              <div class="text-h6 font-weight-bold text-purple-darken-2">KES (KSh)</div>
              <div class="text-caption text-grey">KRA Tax PIN Configured</div>
            </div>
            <v-avatar color="purple-lighten-5" size="44">
              <v-icon icon="mdi-cash" color="purple" size="24"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Executive Performance & Sales Velocity Chart Card -->
    <v-card class="elevation-1 rounded-lg pa-4 mb-4" color="white" v-if="authStore.hasPermission('reports.sales')">
      <div class="d-flex flex-wrap justify-space-between align-center mb-3 ga-2">
        <div class="d-flex align-center">
          <v-avatar color="purple-lighten-5" class="mr-3" size="40">
            <v-icon icon="mdi-chart-timeline-variant" color="purple-darken-2" size="22"></v-icon>
          </v-avatar>
          <div>
            <h2 class="text-subtitle-1 font-weight-bold">7-Day Sales Performance & Revenue Trajectory</h2>
            <p class="text-caption text-grey">Live revenue velocity and gross profit analytics</p>
          </div>
        </div>

        <div class="d-flex align-center ga-2">
          <v-btn
            size="small"
            variant="tonal"
            color="purple-darken-2"
            append-icon="mdi-arrow-right"
            to="/reports"
            class="text-none font-weight-bold"
          >
            Full Business Analytics
          </v-btn>
        </div>
      </div>

      <v-row density="compact">
        <!-- Mini Sparkline Area Chart -->
        <v-col cols="12" md="8">
          <div v-if="loadingStats" class="text-center py-8">
            <v-progress-circular indeterminate color="purple-darken-2" size="32"></v-progress-circular>
            <div class="text-caption text-grey mt-2">Loading sales velocity...</div>
          </div>
          <div v-else-if="hasSalesTrends" class="w-100">
            <apexchart
              key="dashboard-sales-chart"
              type="area"
              height="200"
              :options="chartOptions"
              :series="chartSeries"
            ></apexchart>
          </div>
          <div v-else class="text-center py-8 text-grey">
            <v-icon icon="mdi-chart-line" size="36" class="mb-1 text-grey-lighten-1"></v-icon>
            <div class="text-caption">Awaiting sales transactions for this 7-day window</div>
          </div>
        </v-col>

        <!-- Key Financial Snapshot Column -->
        <v-col cols="12" md="4" class="d-flex flex-column justify-center border-s-md pa-3">
          <div class="mb-3">
            <div class="text-caption font-weight-bold text-grey-darken-1">TODAY'S TURNOVER</div>
            <div class="text-h6 font-weight-bold text-teal-darken-3">
              KES {{ formatNumber(dashboardSummary?.today?.revenue || 0) }}
            </div>
            <div class="text-caption text-grey">
              {{ dashboardSummary?.today?.transactions || 0 }} completed sales transactions
            </div>
          </div>

          <v-divider class="my-2"></v-divider>

          <div class="mb-3">
            <div class="text-caption font-weight-bold text-grey-darken-1">MONTH-TO-DATE REVENUE</div>
            <div class="text-subtitle-1 font-weight-bold text-purple-darken-3">
              KES {{ formatNumber(dashboardSummary?.month_to_date?.revenue || 0) }}
            </div>
            <div class="text-caption text-grey">
              Gross Margin: <strong class="text-teal-darken-2">{{ dashboardSummary?.month_to_date?.gross_margin || 0 }}%</strong>
            </div>
          </div>

          <v-divider class="my-2"></v-divider>

          <div>
            <div class="text-caption font-weight-bold text-grey-darken-1">ON-HAND STOCK VALUATION</div>
            <div class="text-subtitle-1 font-weight-bold text-blue-darken-3">
              KES {{ formatNumber(dashboardSummary?.inventory?.valuation_at_cost || 0) }}
            </div>
            <div class="text-caption text-deep-orange font-weight-medium">
              &le; 30d Expiry Risk: KES {{ formatNumber(dashboardSummary?.inventory?.critical_expiry_risk || 0) }}
            </div>
          </div>
        </v-col>
      </v-row>
    </v-card>

    <!-- Navigation Hub Cards -->
    <v-row class="mb-4" density="compact">
      <v-col cols="12">
        <h2 class="text-subtitle-1 font-weight-bold text-grey-darken-3 mb-3">Core Pharmacy Modules</h2>
      </v-col>

      <v-col cols="12" sm="6" lg="4" v-for="mod in modules" :key="mod.title">
        <v-card
          class="elevation-1 rounded-lg pa-4 h-100 d-flex flex-column justify-space-between transition-swing"
          style="min-height: 175px;"
          hover
          :to="mod.route"
          :disabled="!authStore.hasPermission(mod.permission)"
        >
          <div>
            <!-- Header: Icon Avatar with Title on the Right (text wraps naturally) -->
            <div class="d-flex align-start justify-space-between mb-2">
              <div class="d-flex align-center flex-grow-1 mr-2" style="min-width: 0;">
                <v-avatar :color="mod.color" size="44" rounded="lg" class="elevation-1 mr-3 flex-shrink-0">
                  <v-icon :icon="mod.icon" color="white" size="24"></v-icon>
                </v-avatar>
                <div
                  class="text-subtitle-1 font-weight-bold text-grey-darken-4 text-wrap"
                  style="line-height: 1.25; word-break: normal; overflow-wrap: break-word;"
                >
                  {{ mod.title }}
                </div>
              </div>

              <!-- Status badge or action indicator -->
              <v-chip
                v-if="!authStore.hasPermission(mod.permission)"
                size="x-small"
                color="error"
                variant="tonal"
                class="font-weight-medium text-no-wrap flex-shrink-0 mt-1"
                prepend-icon="mdi-lock"
              >
                Restricted
              </v-chip>
              <v-icon
                v-else
                icon="mdi-arrow-top-right"
                size="18"
                color="grey-darken-1"
                class="opacity-60 flex-shrink-0 mt-1"
              ></v-icon>
            </div>

            <!-- Module Description -->
            <div class="text-caption text-grey-darken-1" style="line-height: 1.45; min-height: 38px;">
              {{ mod.description }}
            </div>
          </div>

          <!-- Bottom Action Row -->
          <div class="text-end border-t pt-2 mt-2">
            <v-btn
              variant="text"
              size="small"
              color="primary"
              append-icon="mdi-arrow-right"
              class="text-none font-weight-bold px-0"
            >
              Open Module
            </v-btn>
          </div>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/api';
import { formatNumber } from '@/utils/formatters';

const authStore = useAuthStore();

const dashboardSummary = ref(null);
const salesTrends = ref([]);
const loadingStats = ref(false);

const hasSalesTrends = computed(() => {
  return salesTrends.value.length > 0 && chartSeries.value[0]?.data?.length > 0;
});

const chartSeries = computed(() => [
  {
    name: 'Sales Revenue (KES)',
    data: salesTrends.value.map((t) => Math.round(t.revenue || 0)),
  },
  {
    name: 'Gross Profit (KES)',
    data: salesTrends.value.map((t) => Math.round(t.gross_profit || 0)),
  },
]);

const chartOptions = computed(() => ({
  chart: {
    id: 'dashboard-sales-chart',
    type: 'area',
    toolbar: { show: false },
    fontFamily: 'inherit',
    sparkline: { enabled: false },
  },
  colors: ['#7B1FA2', '#00897B'],
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  xaxis: {
    categories: salesTrends.value.map((t) => t.date),
    labels: { rotate: -20, style: { fontSize: '10px' } },
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

async function loadDashboardAnalytics() {
  if (!authStore.hasPermission('reports.sales')) return;

  loadingStats.value = true;
  try {
    const today = new Date();
    const sevenDaysAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
    const pad = (n) => String(n).padStart(2, '0');
    const toDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    const [summaryRes, salesRes] = await Promise.all([
      api.get('/reports/dashboard-summary').catch((err) => {
        console.warn('Dashboard summary call failed:', err);
        return null;
      }),
      api.get('/reports/sales-analytics', {
        params: {
          start_date: toDate(sevenDaysAgo),
          end_date: toDate(today),
        },
      }).catch((err) => {
        console.warn('Dashboard sales analytics call failed:', err);
        return null;
      }),
    ]);

    if (summaryRes?.data?.success) {
      dashboardSummary.value = summaryRes.data.data;
    }
    if (salesRes?.data?.success) {
      salesTrends.value = salesRes.data.data.daily_trends || [];
    }
  } catch (error) {
    console.error('Failed to load dashboard analytics:', error);
  } finally {
    loadingStats.value = false;
  }
}

onMounted(() => {
  loadDashboardAnalytics();
});

const modules = [
  {
    title: 'Point of Sale (POS)',
    description: 'Fast barcode cashier counter with FEFO batch depletion, split payments (Cash, M-Pesa, Card), and receipts.',
    icon: 'mdi-cash-register',
    color: 'teal-darken-2',
    route: '/pos',
    permission: 'pos.access',
  },
  {
    title: 'Clinical Dispensing',
    description: 'Doctor prescription registry, patient profiles, clinical history, and prescription dosage tracking.',
    icon: 'mdi-clipboard-plus-outline',
    color: 'blue-darken-2',
    route: '/dispensing',
    permission: 'dispensing.view',
  },
  {
    title: 'Inventory & Batches',
    description: 'Multi-batch stock tracking, FEFO priority queues, expiry risk alerts, and stock adjustments.',
    icon: 'mdi-pill',
    color: 'green-darken-3',
    route: '/inventory',
    permission: 'inventory.view',
  },
  {
    title: 'Procurement & Purchasing',
    description: 'Purchase orders, stock receiving (GRN), supplier invoices, payments, and supplier credit management.',
    icon: 'mdi-truck-delivery-outline',
    color: 'indigo',
    route: '/purchases',
    permission: 'procurement.view_orders',
  },
  {
    title: 'Operating Expenses',
    description: 'Operating expenditure, voucher creation, petty cash tracking, and business expense reports.',
    icon: 'mdi-receipt-text-outline',
    color: 'deep-orange',
    route: '/expenses',
    permission: 'finance.view_expenses',
  },
  {
    title: 'Financial Accounting',
    description: 'Double-entry general ledger, automated COGS, balance sheet, and live profit & loss (P&L) statements.',
    icon: 'mdi-book-open-outline',
    color: 'green-darken-2',
    route: '/accounting',
    permission: 'finance.chart_of_accounts',
  },
  {
    title: 'Business Analytics & Reports',
    description: 'Comprehensive business intelligence, sales trends, supplier performance, and PDF/Excel export.',
    icon: 'mdi-chart-box-outline',
    color: 'purple',
    route: '/reports',
    permission: 'reports.sales',
  },
  {
    title: 'System Settings',
    description: 'Pharmacy details, KRA tax PIN, multi-branch management, alert thresholds, and security roles.',
    icon: 'mdi-cog-outline',
    color: 'grey-darken-3',
    route: '/settings',
    permission: 'settings.manage',
  },
];
</script>
