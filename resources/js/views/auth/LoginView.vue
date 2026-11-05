<template>
  <v-app>
    <v-main class="d-flex align-center justify-center pa-4" style="min-height: 100vh; background: linear-gradient(135deg, #004D40 0%, #00897B 50%, #80CBC4 100%);">
      <div class="d-flex align-center justify-center w-100" style="max-width: 480px;">
        <v-card class="elevation-12 rounded-lg pa-6 w-100" width="100%">
          <div class="text-center mb-6">
            <v-avatar color="primary" size="64" class="mb-3">
              <v-icon icon="mdi-pill" size="36" color="white"></v-icon>
            </v-avatar>
            <h2 class="text-h5 font-weight-bold text-teal-darken-3">Afya Meds PMS</h2>
            <p class="text-caption text-grey-darken-1">Pharmacy Management & Point of Sale System</p>
          </div>

          <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4" density="compact" closable @click:close="errorMessage = ''">
            {{ errorMessage }}
          </v-alert>

          <v-form @submit.prevent="handleLogin">
            <v-text-field
              v-model="email"
              label="Staff Email"
              prepend-inner-icon="mdi-email-outline"
              variant="outlined"
              density="comfortable"
              autocomplete="email"
              required
              :rules="[v => !!v || 'Email is required']"
              class="mb-2"
            ></v-text-field>

            <v-text-field
              v-model="password"
              label="Password"
              prepend-inner-icon="mdi-lock-outline"
              :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
              :type="showPassword ? 'text' : 'password'"
              @click:append-inner="showPassword = !showPassword"
              variant="outlined"
              density="comfortable"
              autocomplete="current-password"
              required
              :rules="[v => !!v || 'Password is required']"
              class="mb-4"
            ></v-text-field>

            <v-btn
              type="submit"
              color="primary"
              block
              size="large"
              class="font-weight-bold text-none mb-4"
              :loading="authStore.loading"
            >
              Sign In
            </v-btn>
          </v-form>

          <v-divider class="my-4"></v-divider>

          <div>
            <p class="text-caption font-weight-medium text-grey-darken-2 mb-2">Quick Demo Access (Click to Fill):</p>
            <v-row density="compact">
              <v-col cols="6" v-for="demo in demoAccounts" :key="demo.email">
                <v-btn
                  variant="outlined"
                  size="x-small"
                  block
                  color="teal-darken-2"
                  class="text-none justify-start px-2 py-1"
                  @click="fillCredentials(demo.email)"
                >
                  <v-icon :icon="demo.icon" start size="14"></v-icon>
                  {{ demo.role }}
                </v-btn>
              </v-col>
            </v-row>
          </div>
        </v-card>
      </div>
    </v-main>
  </v-app>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useNotificationStore } from '@/stores/notification';

const router = useRouter();
const authStore = useAuthStore();
const notify = useNotificationStore();

const email = ref('owner@afyameds.co.ke');
const password = ref('password123');
const showPassword = ref(false);
const errorMessage = ref('');

const demoAccounts = [
  { role: 'Owner (All)', email: 'owner@afyameds.co.ke', icon: 'mdi-shield-crown' },
  { role: 'Pharmacist', email: 'pharmacist@afyameds.co.ke', icon: 'mdi-stethoscope' },
  { role: 'Cashier (POS)', email: 'cashier@afyameds.co.ke', icon: 'mdi-cash-register' },
  { role: 'Inventory Mgr', email: 'inventory@afyameds.co.ke', icon: 'mdi-package-variant-closed' },
  { role: 'Accountant', email: 'accountant@afyameds.co.ke', icon: 'mdi-calculator' },
];

function fillCredentials(selectedEmail) {
  email.value = selectedEmail;
  password.value = 'password123';
  errorMessage.value = '';
}

async function handleLogin() {
  errorMessage.value = '';
  const result = await authStore.login(email.value, password.value);
  if (result.success) {
    notify.success(`Welcome back, ${result.user.name}`);
    router.push('/dashboard');
  } else {
    errorMessage.value = result.message;
  }
}
</script>
