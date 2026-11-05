<template>
  <div>
    <!-- Standard Header Card -->
    <v-card class="elevation-1 rounded-lg pa-4 mb-4" color="white">
      <div class="d-flex flex-wrap justify-space-between align-center ga-3">
        <div class="d-flex align-center">
          <v-avatar color="deep-orange" class="mr-3" size="44">
            <v-icon icon="mdi-receipt-text-outline" color="white" size="24"></v-icon>
          </v-avatar>
          <div>
            <h1 class="text-h6 font-weight-bold">Operating Expenses & Expenditure</h1>
            <p class="text-caption text-grey">Track operating overheads, approve vouchers, and manage expense categories</p>
          </div>
        </div>

        <div class="d-flex flex-wrap ga-2">
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            @click="openCreateVoucherModal"
            class="text-none font-weight-medium"
          >
            Record Expense Voucher
          </v-btn>
          <v-btn
            color="deep-orange"
            variant="outlined"
            prepend-icon="mdi-tag-plus-outline"
            @click="showCategoryModal = true"
            class="text-none font-weight-medium"
          >
            New Category
          </v-btn>
          <v-btn
            icon="mdi-refresh"
            variant="text"
            @click="loadData"
            :loading="loading"
            title="Refresh expenses"
          ></v-btn>
        </div>
      </div>
    </v-card>

    <!-- Quick Metrics KPI Cards (Uniform height & structure) -->
    <v-row density="compact" class="mb-4">
      <v-col cols="12" sm="6" md="3">
        <v-card
          class="elevation-1 rounded-lg pa-4 border-s-lg border-deep-orange h-100 d-flex flex-column justify-center cursor-default"
          style="min-height: 104px;"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <p class="text-caption text-deep-orange font-weight-bold mb-1">TOTAL EXPENSES (THIS MONTH)</p>
              <h3 class="text-h6 font-weight-bold text-deep-orange-darken-3">KES {{ formatNumber(monthlyTotal) }}</h3>
              <p class="text-caption text-grey">{{ vouchers.length }} vouchers recorded</p>
            </div>
            <v-avatar color="deep-orange-lighten-5" size="44">
              <v-icon icon="mdi-cash-minus" color="deep-orange" size="24"></v-icon>
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
              <p class="text-caption text-teal font-weight-bold mb-1">CASH PAYMENTS (TILL/PETTY)</p>
              <h3 class="text-h6 font-weight-bold text-teal-darken-3">KES {{ formatNumber(cashExpensesTotal) }}</h3>
              <p class="text-caption text-grey">Direct drawer disbursements</p>
            </div>
            <v-avatar color="teal-lighten-5" size="44">
              <v-icon icon="mdi-cash" color="teal" size="24"></v-icon>
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
              <p class="text-caption text-blue font-weight-bold mb-1">M-PESA & BANK TRANSFERS</p>
              <h3 class="text-h6 font-weight-bold text-blue-darken-3">KES {{ formatNumber(digitalExpensesTotal) }}</h3>
              <p class="text-caption text-grey">Electronic settlements</p>
            </div>
            <v-avatar color="blue-lighten-5" size="44">
              <v-icon icon="mdi-cellphone-check" color="blue" size="24"></v-icon>
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
              <p class="text-caption text-amber-darken-3 font-weight-bold mb-1">PENDING APPROVAL</p>
              <h3 class="text-h6 font-weight-bold text-amber-darken-4">{{ pendingVouchersCount }}</h3>
              <p class="text-caption text-grey">Vouchers awaiting review</p>
            </div>
            <v-avatar color="amber-lighten-5" size="44">
              <v-icon icon="mdi-clock-alert-outline" color="amber-darken-3" size="24"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Tabs Navigation -->
    <v-tabs v-model="tab" color="primary" class="mb-4 bg-white rounded-lg elevation-1 px-2">
      <v-tab value="vouchers" class="text-none">
        <v-icon start icon="mdi-receipt-outline"></v-icon>
        Expense Vouchers
      </v-tab>
      <v-tab value="categories" class="text-none">
        <v-icon start icon="mdi-shape-outline"></v-icon>
        Expense Categories
      </v-tab>
      <v-tab value="breakdown" class="text-none">
        <v-icon start icon="mdi-chart-pie"></v-icon>
        Monthly Breakdown
      </v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <!-- TAB 1: EXPENSE VOUCHERS -->
      <v-window-item value="vouchers">
        <v-card class="elevation-1 rounded-lg pa-4">
          <!-- Filters Row -->
          <v-row density="compact" class="mb-3" align="center">
            <v-col cols="12" sm="4">
              <v-select
                v-model="selectedCategory"
                :items="categoryOptions"
                item-title="title"
                item-value="id"
                label="Filter by Category"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @update:model-value="loadVouchers"
              ></v-select>
            </v-col>
            <v-col cols="12" sm="3">
              <v-select
                v-model="selectedStatus"
                :items="[
                  { title: 'All Statuses', value: null },
                  { title: 'Approved Only', value: 'approved' },
                  { title: 'Draft / Pending', value: 'draft' },
                  { title: 'Rejected', value: 'rejected' },
                ]"
                item-title="title"
                item-value="value"
                label="Filter Status"
                variant="outlined"
                density="compact"
                hide-details
                @update:model-value="loadVouchers"
              ></v-select>
            </v-col>
            <v-col cols="12" sm="3">
              <v-select
                v-model="selectedMethod"
                :items="[
                  { title: 'All Methods', value: null },
                  { title: 'Cash', value: 'cash' },
                  { title: 'M-Pesa', value: 'mpesa' },
                  { title: 'Bank Transfer', value: 'bank_transfer' },
                  { title: 'Petty Cash', value: 'petty_cash' },
                ]"
                item-title="title"
                item-value="value"
                label="Payment Method"
                variant="outlined"
                density="compact"
                hide-details
                @update:model-value="loadVouchers"
              ></v-select>
            </v-col>
            <v-col cols="12" sm="2" class="text-end">
              <v-btn icon="mdi-refresh" variant="text" @click="loadVouchers" :loading="loadingVouchers"></v-btn>
            </v-col>
          </v-row>

          <v-table hover density="comfortable">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th class="font-weight-bold">Voucher #</th>
                <th class="font-weight-bold">Date & Time</th>
                <th class="font-weight-bold">Category</th>
                <th class="font-weight-bold">Payee / Vendor</th>
                <th class="font-weight-bold text-end">Amount</th>
                <th class="font-weight-bold text-center">Payment Method</th>
                <th class="font-weight-bold text-center">Status</th>
                <th class="font-weight-bold text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="vouchers.length === 0">
                <td colspan="8" class="text-center text-grey py-6">No expense vouchers recorded.</td>
              </tr>
              <tr v-for="v in vouchers" :key="v.id">
                <td class="font-mono font-weight-bold text-caption text-primary" :title="'Voucher: ' + v.voucher_number">
                  {{ v.voucher_number }}
                </td>
                <td class="text-caption font-mono" :title="'Expense Date: ' + v.expense_date">
                  {{ formatDateTime(v.expense_date) }}
                </td>
                <td>
                  <v-chip size="x-small" color="deep-orange" variant="tonal" class="font-weight-medium">
                    {{ v.category?.name || 'General' }}
                  </v-chip>
                </td>
                <td class="font-weight-medium" :title="v.paid_to">
                  {{ v.paid_to }}
                  <div class="text-caption text-grey text-truncate" style="max-width: 200px;">{{ v.description }}</div>
                </td>
                <td class="text-end font-weight-bold font-mono text-body-2">
                  KES {{ formatNumber(v.amount) }}
                </td>
                <td class="text-center">
                  <v-chip size="x-small" :color="getMethodColor(v.payment_method)" variant="outlined" class="text-capitalize">
                    {{ v.payment_method?.replace('_', ' ') }}
                  </v-chip>
                </td>
                <td class="text-center">
                  <v-chip size="x-small" :color="getStatusColor(v.status)" variant="flat" class="text-capitalize font-weight-bold">
                    {{ v.status }}
                  </v-chip>
                </td>
                <td class="text-end">
                  <div class="d-flex justify-end align-center ga-1">
                    <v-btn
                      v-if="v.status === 'draft'"
                      size="x-small"
                      color="success"
                      variant="tonal"
                      @click="approveVoucher(v.id)"
                      title="Approve Voucher"
                    >
                      Approve
                    </v-btn>
                    <v-btn
                      v-if="v.status === 'draft'"
                      size="x-small"
                      color="error"
                      variant="text"
                      @click="rejectVoucher(v.id)"
                      title="Reject Voucher"
                    >
                      Reject
                    </v-btn>
                    <v-btn
                      icon="mdi-eye-outline"
                      size="x-small"
                      variant="text"
                      @click="viewVoucher(v)"
                      title="View Details"
                    ></v-btn>
                  </div>
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <!-- TAB 2: EXPENSE CATEGORIES -->
      <v-window-item value="categories">
        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="d-flex justify-space-between align-center mb-3">
            <div class="text-subtitle-1 font-weight-bold text-grey-darken-3">Chart-Mapped Expense Categories</div>
            <v-btn color="primary" size="small" variant="tonal" prepend-icon="mdi-plus" @click="showCategoryModal = true">
              Add Category
            </v-btn>
          </div>

          <v-table hover density="comfortable">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th class="font-weight-bold">Category Name</th>
                <th class="font-weight-bold">Linked Ledger Account</th>
                <th class="font-weight-bold">Description</th>
                <th class="font-weight-bold text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cat in categories" :key="cat.id">
                <td class="font-weight-bold">{{ cat.name }}</td>
                <td>
                  <span class="font-mono text-caption text-primary font-weight-bold mr-1">
                    [{{ cat.account?.code || '6000' }}]
                  </span>
                  <span>{{ cat.account?.name || 'Operating Expenses' }}</span>
                </td>
                <td class="text-caption text-grey">{{ cat.description || '—' }}</td>
                <td class="text-center">
                  <v-chip size="x-small" :color="cat.is_active ? 'success' : 'grey'" variant="flat">
                    {{ cat.is_active ? 'Active' : 'Inactive' }}
                  </v-chip>
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <!-- TAB 3: MONTHLY BREAKDOWN -->
      <v-window-item value="breakdown">
        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="text-subtitle-1 font-weight-bold text-grey-darken-3 mb-3">Category Expenditure Distribution</div>
          <v-row density="compact">
            <v-col cols="12" md="6" v-for="item in categoryBreakdown" :key="item.name">
              <v-card variant="outlined" class="pa-3 rounded-lg mb-2">
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="font-weight-medium text-body-2">{{ item.name }}</span>
                  <span class="font-weight-bold font-mono text-deep-orange-darken-3">
                    KES {{ formatNumber(item.total) }}
                  </span>
                </div>
                <v-progress-linear
                  :model-value="item.percentage"
                  color="deep-orange"
                  height="6"
                  rounded
                ></v-progress-linear>
                <div class="d-flex justify-space-between text-caption text-grey mt-1">
                  <span>{{ item.count }} voucher(s)</span>
                  <span>{{ item.percentage }}% of total</span>
                </div>
              </v-card>
            </v-col>
          </v-row>
        </v-card>
      </v-window-item>
    </v-window>

    <!-- MODAL 1: CREATE EXPENSE VOUCHER -->
    <v-dialog v-model="showCreateModal" max-width="600px">
      <v-card class="pa-4 rounded-lg">
        <v-card-title class="text-h6 font-weight-bold text-deep-orange-darken-3">
          Record Operating Expense Voucher
        </v-card-title>
        <v-card-text>
          <v-form @submit.prevent="submitVoucher">
            <v-row density="compact">
              <v-col cols="12" sm="6">
                <v-select
                  v-model="voucherForm.expense_category_id"
                  :items="categories"
                  item-title="name"
                  item-value="id"
                  label="Expense Category *"
                  variant="outlined"
                  density="compact"
                  required
                ></v-select>
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="voucherForm.paid_to"
                  label="Payee / Recipient Vendor *"
                  placeholder="e.g. Kenya Power, Plaza Caretaker"
                  variant="outlined"
                  density="compact"
                  required
                ></v-text-field>
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="voucherForm.amount"
                  type="number"
                  step="0.01"
                  label="Amount (KES) *"
                  variant="outlined"
                  density="compact"
                  required
                ></v-text-field>
              </v-col>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="voucherForm.payment_method"
                  :items="[
                    { title: 'Cash in Till', value: 'cash' },
                    { title: 'M-Pesa Paybill / Buy Goods', value: 'mpesa' },
                    { title: 'Bank Transfer / EFT', value: 'bank_transfer' },
                    { title: 'Petty Cash Fund', value: 'petty_cash' },
                    { title: 'Bank Cheque', value: 'cheque' },
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Payment Method *"
                  variant="outlined"
                  density="compact"
                ></v-select>
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="voucherForm.reference_number"
                  label="Reference # / Receipt Code"
                  placeholder="e.g. QK87XW43, Cheque #004"
                  variant="outlined"
                  density="compact"
                ></v-text-field>
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="voucherForm.expense_date"
                  type="date"
                  label="Expense Date"
                  variant="outlined"
                  density="compact"
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="voucherForm.description"
                  label="Expenditure Purpose & Memo"
                  rows="2"
                  variant="outlined"
                  density="compact"
                ></v-textarea>
              </v-col>

              <v-col cols="12">
                <v-checkbox
                  v-model="voucherForm.auto_approve"
                  label="Approve immediately and auto-post to General Ledger"
                  color="success"
                  density="compact"
                  hide-details
                ></v-checkbox>
              </v-col>
            </v-row>

            <v-card-actions class="mt-4 px-0">
              <v-spacer></v-spacer>
              <v-btn variant="text" @click="showCreateModal = false">Cancel</v-btn>
              <v-btn color="primary" type="submit" :loading="savingVoucher" class="font-weight-bold">
                Save & Post Voucher
              </v-btn>
            </v-card-actions>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL 2: ADD CATEGORY -->
    <v-dialog v-model="showCategoryModal" max-width="500px">
      <v-card class="pa-4 rounded-lg">
        <v-card-title class="text-h6 font-weight-bold text-deep-orange-darken-3">
          Create Expense Category
        </v-card-title>
        <v-card-text>
          <v-form @submit.prevent="submitCategory">
            <v-text-field
              v-model="categoryForm.name"
              label="Category Name *"
              placeholder="e.g. Staff Uniforms & PPE"
              variant="outlined"
              density="compact"
              required
              class="mb-3"
            ></v-text-field>

            <v-textarea
              v-model="categoryForm.description"
              label="Description"
              rows="2"
              variant="outlined"
              density="compact"
            ></v-textarea>

            <v-card-actions class="mt-4 px-0">
              <v-spacer></v-spacer>
              <v-btn variant="text" @click="showCategoryModal = false">Cancel</v-btn>
              <v-btn color="primary" type="submit" :loading="savingCategory" class="font-weight-bold">
                Save Category
              </v-btn>
            </v-card-actions>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL 3: VIEW VOUCHER DETAILS -->
    <v-dialog v-model="showViewModal" max-width="500px">
      <v-card class="pa-4 rounded-lg" v-if="selectedVoucher">
        <v-card-title class="d-flex justify-space-between align-center">
          <span class="font-weight-bold">Voucher #{{ selectedVoucher.voucher_number }}</span>
          <v-chip size="x-small" :color="getStatusColor(selectedVoucher.status)" variant="flat" class="text-capitalize">
            {{ selectedVoucher.status }}
          </v-chip>
        </v-card-title>
        <v-card-text>
          <div class="my-2 pa-3 bg-grey-lighten-4 rounded font-mono">
            <div class="d-flex justify-space-between text-body-1 font-weight-bold text-deep-orange-darken-4">
              <span>Amount Paid:</span>
              <span>KES {{ formatNumber(selectedVoucher.amount) }}</span>
            </div>
            <div class="d-flex justify-space-between text-caption text-grey mt-1">
              <span>Payment Method:</span>
              <span class="text-capitalize">{{ selectedVoucher.payment_method?.replace('_', ' ') }}</span>
            </div>
          </div>

          <div class="text-body-2 mb-2"><b>Payee / Vendor:</b> {{ selectedVoucher.paid_to }}</div>
          <div class="text-body-2 mb-2"><b>Category:</b> {{ selectedVoucher.category?.name }}</div>
          <div class="text-body-2 mb-2"><b>Date & Time:</b> {{ formatDateTime(selectedVoucher.expense_date) }}</div>
          <div class="text-body-2 mb-2"><b>Reference Code:</b> {{ selectedVoucher.reference_number || 'N/A' }}</div>
          <div class="text-body-2 mb-2"><b>Description:</b> {{ selectedVoucher.description || '—' }}</div>
          <div class="text-body-2 mb-2" v-if="selectedVoucher.creator"><b>Recorded By:</b> {{ selectedVoucher.creator.name }}</div>
          <div class="text-body-2 mb-2" v-if="selectedVoucher.approver"><b>Approved By:</b> {{ selectedVoucher.approver.name }}</div>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showViewModal = false">Close</v-btn>
        </v-card-actions>
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

const tab = ref('vouchers');
const loading = ref(false);
const loadingVouchers = ref(false);

const vouchers = ref([]);
const categories = ref([]);

const selectedCategory = ref(null);
const selectedStatus = ref(null);
const selectedMethod = ref(null);

const showCreateModal = ref(false);
const showCategoryModal = ref(false);
const showViewModal = ref(false);
const selectedVoucher = ref(null);

const savingVoucher = ref(false);
const savingCategory = ref(false);

const voucherForm = ref({
  expense_category_id: null,
  paid_to: '',
  amount: null,
  tax_amount: 0,
  payment_method: 'cash',
  reference_number: '',
  expense_date: new Date().toISOString().substring(0, 10),
  description: '',
  auto_approve: true,
});

const categoryForm = ref({
  name: '',
  description: '',
});

// Computed Metrics
const monthlyTotal = computed(() => {
  return vouchers.value
    .filter(v => v.status === 'approved')
    .reduce((sum, v) => sum + Number(v.amount || 0), 0);
});

const cashExpensesTotal = computed(() => {
  return vouchers.value
    .filter(v => v.status === 'approved' && (v.payment_method === 'cash' || v.payment_method === 'petty_cash'))
    .reduce((sum, v) => sum + Number(v.amount || 0), 0);
});

const digitalExpensesTotal = computed(() => {
  return vouchers.value
    .filter(v => v.status === 'approved' && (v.payment_method === 'mpesa' || v.payment_method === 'bank_transfer'))
    .reduce((sum, v) => sum + Number(v.amount || 0), 0);
});

const pendingVouchersCount = computed(() => {
  return vouchers.value.filter(v => v.status === 'draft').length;
});

const categoryOptions = computed(() => {
  return [
    { title: 'All Categories', id: null },
    ...categories.value.map(c => ({ title: c.name, id: c.id })),
  ];
});

const categoryBreakdown = computed(() => {
  const map = {};
  const total = monthlyTotal.value || 1;

  vouchers.value
    .filter(v => v.status === 'approved')
    .forEach(v => {
      const name = v.category?.name || 'General';
      if (!map[name]) {
        map[name] = { name, total: 0, count: 0 };
      }
      map[name].total += Number(v.amount || 0);
      map[name].count += 1;
    });

  return Object.values(map).map(item => ({
    ...item,
    percentage: ((item.total / total) * 100).toFixed(1),
  }));
});

function getMethodColor(method) {
  switch (method) {
    case 'cash': return 'teal';
    case 'mpesa': return 'success';
    case 'bank_transfer': return 'blue';
    case 'petty_cash': return 'purple';
    default: return 'grey';
  }
}

function getStatusColor(status) {
  switch (status) {
    case 'approved': return 'success';
    case 'draft': return 'amber-darken-3';
    case 'rejected': return 'error';
    default: return 'grey';
  }
}

function openCreateVoucherModal() {
  voucherForm.value = {
    expense_category_id: categories.value[0]?.id || null,
    paid_to: '',
    amount: null,
    tax_amount: 0,
    payment_method: 'cash',
    reference_number: '',
    expense_date: new Date().toISOString().substring(0, 10),
    description: '',
    auto_approve: true,
  };
  showCreateModal.value = true;
}

function viewVoucher(v) {
  selectedVoucher.value = v;
  showViewModal.value = true;
}

async function loadData() {
  loading.value = true;
  await Promise.all([loadCategories(), loadVouchers()]);
  loading.value = false;
}

async function loadCategories() {
  try {
    const res = await api.get('/expenses/categories');
    if (res.data?.success) {
      categories.value = res.data.data;
    }
  } catch (err) {
    console.error('Failed to load expense categories', err);
  }
}

async function loadVouchers() {
  loadingVouchers.value = true;
  try {
    const params = {};
    if (selectedCategory.value) params.category_id = selectedCategory.value;
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (selectedMethod.value) params.payment_method = selectedMethod.value;

    const res = await api.get('/expenses/vouchers', { params });
    if (res.data?.success) {
      vouchers.value = res.data.data;
    }
  } catch (err) {
    console.error('Failed to load vouchers', err);
  } finally {
    loadingVouchers.value = false;
  }
}

async function submitVoucher() {
  savingVoucher.value = true;
  try {
    const res = await api.post('/expenses/vouchers', voucherForm.value);
    if (res.data?.success) {
      notify.success('Expense voucher saved and posted to General Ledger.');
      showCreateModal.value = false;
      await loadVouchers();
    }
  } catch (err) {
    notify.error(err.response?.data?.message || 'Failed to record expense voucher');
  } finally {
    savingVoucher.value = false;
  }
}

async function submitCategory() {
  savingCategory.value = true;
  try {
    const res = await api.post('/expenses/categories', categoryForm.value);
    if (res.data?.success) {
      notify.success('Category created successfully.');
      showCategoryModal.value = false;
      categoryForm.value = { name: '', description: '' };
      await loadCategories();
    }
  } catch (err) {
    notify.error(err.response?.data?.message || 'Failed to create category');
  } finally {
    savingCategory.value = false;
  }
}

async function approveVoucher(id) {
  try {
    const res = await api.put(`/expenses/vouchers/${id}/approve`);
    if (res.data?.success) {
      notify.success('Voucher approved and posted to General Ledger.');
      await loadVouchers();
    }
  } catch (err) {
    notify.error('Failed to approve voucher');
  }
}

async function rejectVoucher(id) {
  try {
    const res = await api.put(`/expenses/vouchers/${id}/reject`, { reason: 'Declined by reviewer' });
    if (res.data?.success) {
      notify.info('Voucher marked as rejected.');
      await loadVouchers();
    }
  } catch (err) {
    notify.error('Failed to reject voucher');
  }
}

onMounted(() => {
  loadData();
});
</script>
