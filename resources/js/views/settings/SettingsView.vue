<template>
  <div>
    <!-- Standard Header Card -->
    <v-card class="elevation-1 rounded-lg pa-4 mb-4" color="white">
      <div class="d-flex flex-wrap justify-space-between align-center ga-3">
        <div class="d-flex align-center">
          <v-avatar color="grey-darken-3" class="mr-3" size="44">
            <v-icon icon="mdi-cog-outline" color="white" size="24"></v-icon>
          </v-avatar>
          <div>
            <h1 class="text-h6 font-weight-bold">System Settings & Configurations</h1>
            <p class="text-caption text-grey">Configure pharmacy profile, KRA tax PIN, and alert thresholds</p>
          </div>
        </div>

        <div class="d-flex ga-2">
          <v-btn
            v-if="tab !== 'maintenance'"
            color="primary"
            prepend-icon="mdi-content-save"
            :loading="saving"
            @click="saveSettings"
            class="font-weight-medium text-none"
          >
            Save Settings
          </v-btn>
          <v-btn
            v-else
            color="primary"
            prepend-icon="mdi-database-export-outline"
            :loading="generatingBackup"
            @click="triggerBackup"
            class="font-weight-medium text-none"
          >
            Generate Database Backup
          </v-btn>
        </div>
      </div>
    </v-card>

    <!-- Standard Tab Navigation -->
    <v-tabs v-model="tab" color="primary" class="mb-4 bg-white rounded-lg elevation-1 px-2" grow>
      <v-tab value="general" class="text-none font-weight-medium">
        <v-icon start icon="mdi-domain"></v-icon>
        General & Profile
      </v-tab>
      <v-tab value="inventory" class="text-none font-weight-medium">
        <v-icon start icon="mdi-pill"></v-icon>
        Inventory & Expiries
      </v-tab>
      <v-tab value="pos" class="text-none font-weight-medium">
        <v-icon start icon="mdi-receipt-text-outline"></v-icon>
        POS & Receipts
      </v-tab>
      <v-tab value="maintenance" class="text-none font-weight-medium">
        <v-icon start icon="mdi-database-cog-outline"></v-icon>
        Backups & Maintenance
      </v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <!-- General Tab -->
      <v-window-item value="general">
        <v-card class="elevation-1 rounded-lg pa-4">
          <v-row density="compact">
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.company_name"
                label="Company / Pharmacy Name"
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.tax_pin"
                label="Tax PIN (KRA PIN)"
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.currency_code"
                label="Currency Code"
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.currency_symbol"
                label="Currency Symbol"
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
          </v-row>
        </v-card>
      </v-window-item>

      <!-- Inventory Tab -->
      <v-window-item value="inventory">
        <v-card class="elevation-1 rounded-lg pa-4">
          <v-row density="compact">
            <v-col cols="12" md="4">
              <v-text-field
                v-model.number="form.low_stock_threshold"
                type="number"
                label="Default Low Stock Threshold"
                hint="Triggers reorder alert when batch quantity drops below this"
                persistent-hint
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field
                v-model.number="form.expiry_alert_days_first"
                type="number"
                label="Early Expiry Warning (Days)"
                hint="First yellow alert threshold (default 90 days)"
                persistent-hint
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field
                v-model.number="form.expiry_alert_days_critical"
                type="number"
                label="Critical Expiry Warning (Days)"
                hint="Critical amber/red alert threshold (default 30 days)"
                persistent-hint
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
            <v-col cols="12" class="mt-2">
              <v-switch
                v-model="form.block_expired_sales"
                color="primary"
                label="Strictly Block Sales and Dispensing of Expired Batches"
                hide-details
              ></v-switch>
            </v-col>
          </v-row>
        </v-card>
      </v-window-item>

      <!-- POS Tab -->
      <v-window-item value="pos">
        <v-card class="elevation-1 rounded-lg pa-4">
          <v-row density="compact">
            <v-col cols="12" md="6">
              <v-textarea
                v-model="form.receipt_header"
                label="Thermal Receipt Header"
                rows="3"
                variant="outlined"
                density="compact"
              ></v-textarea>
            </v-col>
            <v-col cols="12" md="6">
              <v-textarea
                v-model="form.receipt_footer"
                label="Thermal Receipt Footer"
                rows="3"
                variant="outlined"
                density="compact"
              ></v-textarea>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model.number="form.default_prescription_tax"
                type="number"
                label="Prescription Medicine VAT (%)"
                hint="Usually 0% exempt for pharmaceuticals"
                persistent-hint
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model.number="form.default_general_tax"
                type="number"
                label="General / Sundry VAT (%)"
                hint="Standard 16% VAT"
                persistent-hint
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-switch
                v-model="form.enable_split_payments"
                color="primary"
                label="Enable Split Payments (Cash, M-Pesa, Card, Credit)"
                hide-details
              ></v-switch>
            </v-col>
            <v-col cols="12" md="6">
              <v-switch
                v-model="form.allow_credit_sales"
                color="primary"
                label="Allow Customer Credit Accounts"
                hide-details
              ></v-switch>
            </v-col>
          </v-row>
        </v-card>
      </v-window-item>

      <!-- TAB 4: BACKUPS & SYSTEM MAINTENANCE -->
      <v-window-item value="maintenance">
        <!-- 4 Uniform Diagnostic KPI Cards (Strict 104px uniform height) -->
        <v-row density="compact" class="mb-4">
          <v-col cols="12" sm="6" md="3">
            <v-card
              class="elevation-1 rounded-lg pa-4 border-s-lg border-teal h-100 d-flex flex-column justify-center cursor-default"
              style="min-height: 104px;"
            >
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-teal-darken-3 font-weight-bold mb-1">SYSTEM HEALTH</p>
                  <h3 class="text-h6 font-weight-bold text-teal-darken-4 text-capitalize">
                    {{ healthData?.status || 'Healthy' }}
                  </h3>
                  <p class="text-caption text-grey">
                    DB Latency: {{ healthData?.database?.latency_ms || 0 }} ms
                  </p>
                </div>
                <v-avatar color="teal-lighten-5" size="44">
                  <v-icon icon="mdi-heart-pulse" color="teal-darken-2" size="24"></v-icon>
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
                  <p class="text-caption text-blue-darken-3 font-weight-bold mb-1">BACKUPS ON DISK</p>
                  <h3 class="text-h6 font-weight-bold text-blue-darken-4">
                    {{ backupsList.length }} File{{ backupsList.length === 1 ? '' : 's' }}
                  </h3>
                  <p class="text-caption text-grey">
                    Storage: storage/app/backups
                  </p>
                </div>
                <v-avatar color="blue-lighten-5" size="44">
                  <v-icon icon="mdi-database" color="blue-darken-2" size="24"></v-icon>
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
                  <p class="text-caption text-purple-darken-3 font-weight-bold mb-1">LATEST BACKUP</p>
                  <h3 class="text-body-2 font-weight-bold text-purple-darken-4">
                    {{ healthData?.backups?.latest?.size || 'N/A' }}
                  </h3>
                  <p class="text-caption text-grey font-mono text-truncate" style="max-width: 160px;">
                    {{ healthData?.backups?.latest?.created_at || 'Never' }}
                  </p>
                </div>
                <v-avatar color="purple-lighten-5" size="44">
                  <v-icon icon="mdi-history" color="purple-darken-2" size="24"></v-icon>
                </v-avatar>
              </div>
            </v-card>
          </v-col>

          <v-col cols="12" sm="6" md="3">
            <v-card
              class="elevation-1 rounded-lg pa-4 border-s-lg border-indigo h-100 d-flex flex-column justify-center cursor-default"
              style="min-height: 104px;"
            >
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-indigo font-weight-bold mb-1">ENVIRONMENT & TIME</p>
                  <h3 class="text-body-2 font-weight-bold text-indigo-darken-4">
                    PHP {{ healthData?.environment?.php_version || '8.3' }} ({{ healthData?.environment?.app_env || 'local' }})
                  </h3>
                  <p class="text-caption text-grey font-mono">
                    {{ healthData?.environment?.server_timezone || 'UTC' }}
                  </p>
                </div>
                <v-avatar color="indigo-lighten-5" size="44">
                  <v-icon icon="mdi-server" color="indigo" size="24"></v-icon>
                </v-avatar>
              </div>
            </v-card>
          </v-col>
        </v-row>

        <!-- Backups List & Management -->
        <v-card class="elevation-1 rounded-lg pa-4 mb-4">
          <div class="d-flex flex-wrap justify-space-between align-center mb-3 ga-2">
            <div>
              <h2 class="text-subtitle-1 font-weight-bold d-flex align-center">
                <v-icon icon="mdi-database-clock-outline" class="mr-2" color="primary" size="20"></v-icon>
                Database Snapshots & Backups
              </h2>
              <p class="text-caption text-grey">Auto-scheduled daily at 02:00 AM with 14-day retention. On-demand backups can be initiated below.</p>
            </div>

            <div class="d-flex ga-2">
              <v-btn
                color="primary"
                size="small"
                prepend-icon="mdi-plus"
                :loading="generatingBackup"
                @click="triggerBackup"
                class="text-none font-weight-medium"
              >
                Create Backup Now
              </v-btn>
              <v-btn
                variant="outlined"
                size="small"
                icon="mdi-refresh"
                :loading="loadingBackups"
                @click="loadMaintenanceData"
                title="Refresh snapshots"
              ></v-btn>
            </div>
          </div>

          <v-data-table
            :headers="backupHeaders"
            :items="backupsList"
            :loading="loadingBackups"
            density="compact"
            class="elevation-0 border rounded"
            :items-per-page="5"
          >
            <template v-slot:item.filename="{ item }">
              <div class="d-flex align-center font-mono text-body-2">
                <v-icon icon="mdi-file-document-outline" size="18" class="mr-2 text-grey-darken-1"></v-icon>
                <span class="text-truncate" style="max-width: 320px;">
                  <v-tooltip activator="parent" location="top">{{ item.filename }}</v-tooltip>
                  {{ item.filename }}
                </span>
              </div>
            </template>
            <template v-slot:item.size="{ item }">
              <v-chip size="x-small" color="blue" variant="tonal" class="font-weight-bold">
                {{ item.size }}
              </v-chip>
            </template>
            <template v-slot:item.created_at="{ item }">
              <span class="font-mono text-caption">{{ item.created_at }}</span>
            </template>
            <template v-slot:item.actions="{ item }">
              <v-btn
                color="primary"
                size="x-small"
                variant="tonal"
                prepend-icon="mdi-download"
                @click="downloadBackup(item.filename)"
                class="text-none"
              >
                Download SQL
              </v-btn>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>
    </v-window>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import { useNotificationStore } from '@/stores/notification';
import { formatDateTime } from '@/utils/formatters';

const notify = useNotificationStore();

const tab = ref('general');
const saving = ref(false);
const loadingBackups = ref(false);
const generatingBackup = ref(false);

const healthData = ref(null);
const backupsList = ref([]);

const backupHeaders = [
  { title: 'Snapshot Filename', key: 'filename', sortable: true },
  { title: 'File Size', key: 'size', sortable: true },
  { title: 'Timestamp', key: 'created_at', sortable: true },
  { title: 'Action', key: 'actions', align: 'end', sortable: false },
];

const form = ref({
  company_name: '',
  tax_pin: '',
  currency_code: 'KES',
  currency_symbol: 'KES',
  low_stock_threshold: 10,
  expiry_alert_days_first: 90,
  expiry_alert_days_critical: 30,
  block_expired_sales: true,
  receipt_header: '',
  receipt_footer: '',
  default_prescription_tax: 0,
  default_general_tax: 16,
  enable_split_payments: true,
  allow_credit_sales: true,
});

const loadSettings = async () => {
  try {
    const response = await api.get('/settings');
    const settings = response.data.data;
    form.value = { ...form.value, ...settings };
  } catch (error) {
    notify.error('Could not load system settings');
  }
};

const saveSettings = async () => {
  saving.value = true;
  try {
    const response = await api.post('/settings', { settings: form.value });
    notify.success(response.data.message || 'Settings saved successfully');
  } catch (error) {
    notify.error(error.response?.data?.message || 'Failed to save settings');
  } finally {
    saving.value = false;
  }
};

const loadMaintenanceData = async () => {
  loadingBackups.value = true;
  try {
    const [healthRes, backupsRes] = await Promise.all([
      api.get('/system/health'),
      api.get('/system/backups'),
    ]);

    if (healthRes.data?.success) {
      healthData.value = healthRes.data.data;
    }
    if (backupsRes.data?.success) {
      backupsList.value = backupsRes.data.data;
    }
  } catch (error) {
    notify.error('Could not load system diagnostics or backups');
  } finally {
    loadingBackups.value = false;
  }
};

const triggerBackup = async () => {
  generatingBackup.value = true;
  try {
    const response = await api.post('/system/backups');
    if (response.data?.success) {
      notify.success(response.data.message || 'Database snapshot created successfully');
      await loadMaintenanceData();
    } else {
      notify.error(response.data?.message || 'Backup completed with warnings');
    }
  } catch (error) {
    notify.error(error.response?.data?.message || 'Failed to trigger backup');
  } finally {
    generatingBackup.value = false;
  }
};

const downloadBackup = async (filename) => {
  try {
    const response = await api.get(`/system/backups/${filename}/download`, {
      responseType: 'blob',
    });

    const blob = new Blob([response.data], { type: 'application/sql' });
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(downloadUrl);

    notify.success(`${filename} downloaded successfully!`);
  } catch (error) {
    notify.error('Failed to download backup file');
  }
};

onMounted(() => {
  loadSettings();
  loadMaintenanceData();
});
</script>
