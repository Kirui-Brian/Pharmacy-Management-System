<template>
  <v-app>
    <!-- Navigation Drawer -->
    <v-navigation-drawer
      v-model="drawer"
      :rail="!mobile && rail"
      :permanent="!mobile"
      :temporary="mobile"
      color="white"
      class="elevation-1"
    >
      <div class="pa-4 d-flex align-center justify-space-between border-b">
        <div class="d-flex align-center" v-if="!rail || mobile">
          <v-avatar color="primary" size="36" class="mr-3">
            <v-icon icon="mdi-pill" color="white" size="20"></v-icon>
          </v-avatar>
          <div>
            <div class="text-subtitle-2 font-weight-bold text-teal-darken-4">Afya Meds</div>
            <div class="text-caption text-grey">Pharmacy PMS</div>
          </div>
        </div>
        <v-btn
          v-if="!mobile"
          variant="text"
          density="comfortable"
          :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
          @click.stop="rail = !rail"
        ></v-btn>
        <v-btn
          v-else
          variant="text"
          density="comfortable"
          icon="mdi-close"
          @click.stop="drawer = false"
        ></v-btn>
      </div>

      <!-- Navigation List -->
      <v-list density="comfortable" nav class="mt-2" color="primary" @click:select="mobile && (drawer = false)">
        <v-list-item
          prepend-icon="mdi-view-dashboard-outline"
          title="Dashboard"
          value="dashboard"
          to="/dashboard"
        ></v-list-item>

        <v-list-item
          v-if="authStore.hasPermission('pos.access')"
          prepend-icon="mdi-cash-register"
          title="Point of Sale (POS)"
          value="pos"
          to="/pos"
        >
          <template v-slot:append v-if="!rail">
            <v-chip color="teal" size="x-small" variant="flat">F2</v-chip>
          </template>
        </v-list-item>

        <v-list-item
          v-if="authStore.hasPermission('dispensing.view')"
          prepend-icon="mdi-clipboard-plus-outline"
          title="Dispensing"
          value="dispensing"
          to="/dispensing"
        ></v-list-item>

        <v-list-item
          v-if="authStore.hasPermission('inventory.view')"
          prepend-icon="mdi-package-variant-closed"
          title="Inventory & Batches"
          value="inventory"
          to="/inventory"
        ></v-list-item>

        <v-list-item
          v-if="authStore.hasPermission('procurement.view_orders')"
          prepend-icon="mdi-truck-delivery-outline"
          title="Procurement & GRN"
          value="purchases"
          to="/purchases"
        ></v-list-item>

        <v-list-item
          v-if="authStore.hasPermission('finance.view_expenses')"
          prepend-icon="mdi-receipt-text-outline"
          title="Expenses"
          value="expenses"
          to="/expenses"
        ></v-list-item>

        <v-list-item
          v-if="authStore.hasPermission('finance.chart_of_accounts')"
          prepend-icon="mdi-book-open-outline"
          title="Accounting"
          value="accounting"
          to="/accounting"
        ></v-list-item>

        <v-list-item
          v-if="authStore.hasPermission('reports.sales')"
          prepend-icon="mdi-chart-box-outline"
          title="Reports & Analytics"
          value="reports"
          to="/reports"
        ></v-list-item>

        <v-divider class="my-2"></v-divider>

        <v-list-item
          v-if="authStore.hasPermission('settings.manage')"
          prepend-icon="mdi-cog-outline"
          title="Settings"
          value="settings"
          to="/settings"
        ></v-list-item>
      </v-list>
    </v-navigation-drawer>

    <!-- Top App Bar -->
    <v-app-bar elevation="0" color="white" class="border-b px-3">
      <v-app-bar-nav-icon @click.stop="drawer = !drawer" class="d-md-none"></v-app-bar-nav-icon>

      <!-- Active Branch Selector -->
      <div class="d-flex align-center">
        <v-chip
          color="teal-lighten-5"
          text-color="teal-darken-3"
          class="font-weight-medium mr-2 cursor-default"
          size="default"
          :title="'Current Operating Branch: ' + (authStore.branch?.name || 'Main Branch') + ' (' + (authStore.branch?.code || 'KUR-01') + ')'"
        >
          <v-icon start icon="mdi-storefront-outline" color="teal"></v-icon>
          {{ authStore.branch?.name || 'Main Branch' }}
          <span class="text-caption text-grey ml-1 font-weight-bold">({{ authStore.branch?.code || 'KUR-01' }})</span>
        </v-chip>

        <!-- Branch Switcher for multi-branch users -->
        <v-menu v-if="authStore.accessibleBranches.length > 1">
          <template v-slot:activator="{ props }">
            <v-btn v-bind="props" variant="text" size="small" append-icon="mdi-chevron-down" color="grey-darken-2" class="text-none">
              Switch Branch
            </v-btn>
          </template>
          <v-list density="compact">
            <v-list-item
              v-for="b in authStore.accessibleBranches"
              :key="b.id"
              :title="b.name"
              :subtitle="b.code"
              :active="b.id === authStore.branch?.id"
              @click="handleBranchSwitch(b.id)"
            ></v-list-item>
          </v-list>
        </v-menu>
      </div>

      <v-spacer></v-spacer>

      <!-- Quick POS Button -->
      <v-btn
        v-if="authStore.hasPermission('pos.access')"
        color="primary"
        variant="elevated"
        class="text-none mr-3 font-weight-bold"
        prepend-icon="mdi-cash-register"
        to="/pos"
      >
        POS Menu (F2)
      </v-btn>

      <!-- User Menu -->
      <v-menu min-width="220px" rounded>
        <template v-slot:activator="{ props }">
          <v-btn icon v-bind="props">
            <v-avatar color="teal-darken-1" size="36">
              <span class="text-white text-body-2 font-weight-bold">
                {{ userInitials }}
              </span>
            </v-avatar>
          </v-btn>
        </template>
        <v-card class="pa-2">
          <v-card-text>
            <div class="font-weight-bold text-subtitle-2">{{ authStore.user?.name }}</div>
            <div class="text-caption text-grey">{{ authStore.user?.email }}</div>
            <v-chip size="x-small" color="primary" class="mt-2 text-capitalize">
              {{ authStore.roles[0] || 'Staff' }}
            </v-chip>
            <div v-if="authStore.user?.license_number" class="text-caption text-teal font-weight-medium mt-1">
              Lic: {{ authStore.user.license_number }}
            </div>
          </v-card-text>
          <v-divider></v-divider>
          <v-list density="compact">
            <v-list-item prepend-icon="mdi-logout" title="Sign Out" @click="handleLogout" color="error"></v-list-item>
          </v-list>
        </v-card>
      </v-menu>
    </v-app-bar>

    <!-- Main Content -->
    <v-main style="background-color: #F8FAFC;">
      <v-container fluid class="pa-2 pa-sm-4 pa-md-6">
        <router-view />
      </v-container>
    </v-main>

    <!-- Global Notification Snackbar -->
    <v-snackbar
      v-model="notifyStore.visible"
      :color="notifyStore.color"
      :timeout="notifyStore.timeout"
      location="top right"
    >
      <div class="d-flex align-center">
        <v-icon
          :icon="notifyStore.color === 'success' ? 'mdi-check-circle' : notifyStore.color === 'error' ? 'mdi-alert-circle' : 'mdi-information'"
          class="mr-2"
        ></v-icon>
        {{ notifyStore.text }}
      </div>
      <template v-slot:actions>
        <v-btn variant="text" @click="notifyStore.visible = false">Close</v-btn>
      </template>
    </v-snackbar>
  </v-app>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useDisplay } from 'vuetify';
import { useAuthStore } from '@/stores/auth';
import { useNotificationStore } from '@/stores/notification';

const router = useRouter();
const authStore = useAuthStore();
const notifyStore = useNotificationStore();
const { mobile } = useDisplay();

const drawer = ref(!mobile.value);
const rail = ref(false);

const userInitials = computed(() => {
  const name = authStore.user?.name || '';
  return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
});

async function handleBranchSwitch(branchId) {
  const res = await authStore.switchBranch(branchId);
  if (res.success) {
    notifyStore.success(res.message);
  } else {
    notifyStore.error(res.message);
  }
}

async function handleLogout() {
  await authStore.logout();
}

function handleKeydown(e) {
  if (e.key === 'F2') {
    e.preventDefault();
    if (authStore.hasPermission('pos.access')) {
      router.push('/pos');
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
});
</script>
