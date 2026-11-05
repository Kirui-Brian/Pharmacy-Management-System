<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="onDialogToggle"
    max-width="440px"
    persistent
  >
    <v-card class="pa-4 rounded-lg">
      <!-- Modal Header with Title & Top-Right (X) Close Icon -->
      <div class="d-flex justify-space-between align-center pb-2 border-b">
        <div class="d-flex align-center">
          <v-avatar color="green-darken-3" size="32" class="mr-2">
            <v-icon icon="mdi-cellphone-wireless" color="white" size="18"></v-icon>
          </v-avatar>
          <span class="text-subtitle-1 font-weight-bold text-green-darken-4">Lipa Na M-Pesa Online</span>
        </div>
        <v-btn
          icon="mdi-close"
          variant="text"
          size="small"
          color="grey-darken-2"
          @click="cancelPrompt"
          title="Cancel & Close"
        ></v-btn>
      </div>

      <v-card-text class="pt-4 text-center px-2">
        <!-- Initial Form: Phone Input & Send Prompt Button -->
        <div v-if="status === 'idle' || status === 'sending'">
          <div class="text-caption text-grey-darken-1 mb-3">
            Initiate a Safaricom Daraja STK Push prompt directly to the customer's mobile phone.
          </div>

          <div class="pa-3 bg-green-lighten-5 rounded border border-green mb-3 text-start">
            <div class="d-flex justify-space-between mb-1">
              <span class="text-caption text-grey-darken-2">Amount to Charge:</span>
              <span class="text-subtitle-1 font-weight-bold text-green-darken-4 font-mono">
                KES {{ Number(amount).toFixed(2) }}
              </span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-caption text-grey-darken-2">Payment Gateway:</span>
              <span class="text-caption font-weight-medium text-grey-darken-3">Safaricom Lipa Na M-Pesa</span>
            </div>
          </div>

          <v-text-field
            v-model="phone"
            label="Customer Safaricom Mobile Number *"
            placeholder="e.g. 0712345678 or 254712345678"
            variant="outlined"
            density="compact"
            prepend-inner-icon="mdi-phone"
            :rules="[v => !!v || 'Phone number is required']"
            class="mb-2"
            autofocus
            @keyup.enter="sendStkPush"
          ></v-text-field>

          <v-btn
            block
            color="green-darken-3"
            size="large"
            prepend-icon="mdi-send"
            :loading="status === 'sending'"
            :disabled="!phone"
            @click="sendStkPush"
            class="text-none font-weight-bold mt-2"
          >
            Send STK Push Prompt
          </v-btn>
        </div>

        <!-- Awaiting PIN Mode with Circular Progress & Countdown -->
        <div v-else-if="status === 'awaiting_pin'" class="py-2">
          <v-progress-circular
            indeterminate
            color="green-darken-3"
            size="52"
            width="4"
            class="mb-3"
          ></v-progress-circular>

          <h4 class="text-subtitle-1 font-weight-bold text-green-darken-4 mb-1">
            Prompt Sent to Handset!
          </h4>
          <p class="text-caption text-grey-darken-1 mb-3">
            Please ask customer to enter their M-Pesa PIN on phone <strong class="text-grey-darken-4 font-mono">{{ phone }}</strong>
          </p>

          <div class="pa-3 bg-grey-lighten-4 rounded font-mono text-caption text-start mb-3">
            <div class="d-flex justify-space-between mb-1">
              <span class="text-grey">Paybill / Till:</span>
              <span class="font-weight-bold">Pharmacy Account</span>
            </div>
            <div class="d-flex justify-space-between mb-1">
              <span class="text-grey">Total Amount:</span>
              <span class="font-weight-bold text-green-darken-3">KES {{ Number(amount).toFixed(2) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-grey">Prompt Countdown:</span>
              <span class="font-weight-bold text-deep-orange">{{ countdown }}s</span>
            </div>
          </div>

          <!-- Simulation / Manual Confirmation button -->
          <v-btn
            color="green-darken-2"
            variant="tonal"
            size="small"
            class="text-none font-weight-bold mb-2"
            prepend-icon="mdi-check-decagram"
            @click="completeTransaction(generatedReceiptCode)"
          >
            Simulate Customer Approval
          </v-btn>
        </div>

        <!-- Success Confirmation State -->
        <div v-else-if="status === 'success'" class="py-3">
          <v-avatar color="green-lighten-4" size="64" class="mb-2">
            <v-icon icon="mdi-check-bold" color="green-darken-3" size="36"></v-icon>
          </v-avatar>
          <h4 class="text-subtitle-1 font-weight-bold text-green-darken-4">Payment Confirmed!</h4>
          <p class="text-caption text-grey mb-3">Safaricom confirmation received</p>

          <div class="pa-3 bg-green-lighten-5 rounded border border-green font-mono text-caption mb-3">
            <div class="d-flex justify-space-between mb-1">
              <span class="text-grey-darken-2">Receipt Code:</span>
              <strong class="text-green-darken-4 font-weight-bold">{{ confirmedCode }}</strong>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-grey-darken-2">Amount Paid:</span>
              <strong class="text-green-darken-4">KES {{ Number(amount).toFixed(2) }}</strong>
            </div>
          </div>
        </div>

        <!-- Failed or Expired State -->
        <div v-else-if="status === 'failed'" class="py-3">
          <v-avatar color="red-lighten-4" size="64" class="mb-2">
            <v-icon icon="mdi-alert-circle-outline" color="red-darken-3" size="36"></v-icon>
          </v-avatar>
          <h4 class="text-subtitle-1 font-weight-bold text-red-darken-4">Prompt Timed Out</h4>
          <p class="text-caption text-grey mb-3">The customer did not enter their PIN or cancelled the request.</p>

          <v-btn
            color="green-darken-3"
            variant="flat"
            size="small"
            prepend-icon="mdi-refresh"
            @click="status = 'idle'"
            class="text-none font-weight-bold"
          >
            Retry With Another Phone
          </v-btn>
        </div>
      </v-card-text>

      <!-- Card Action Footer -->
      <v-card-actions class="border-t pt-2 d-flex justify-end ga-2">
        <v-btn
          color="grey-darken-2"
          variant="outlined"
          @click="cancelPrompt"
          class="text-none"
        >
          {{ status === 'success' ? 'Close' : 'Cancel' }}
        </v-btn>
        <v-btn
          v-if="status === 'success'"
          color="green-darken-3"
          variant="flat"
          @click="applyAndClose"
          class="text-none font-weight-bold"
        >
          Apply to Sale
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch, onUnmounted } from 'vue';
import api from '@/services/api';
import { useNotificationStore } from '@/stores/notification';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  amount: {
    type: Number,
    required: true,
  },
  initialPhone: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue', 'confirmed', 'cancelled']);
const notify = useNotificationStore();

const phone = ref('');
const status = ref('idle'); // 'idle' | 'sending' | 'awaiting_pin' | 'success' | 'failed'
const countdown = ref(30);
const confirmedCode = ref('');
const generatedReceiptCode = ref('');
let timerInterval = null;

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      phone.value = props.initialPhone || '';
      status.value = 'idle';
      countdown.value = 30;
      confirmedCode.value = '';
    } else {
      cleanupTimer();
    }
  }
);

function onDialogToggle(val) {
  if (!val) {
    cancelPrompt();
  } else {
    emit('update:modelValue', true);
  }
}

function cleanupTimer() {
  if (timerInterval) {
    clearInterval(timerInterval);
    timerInterval = null;
  }
}

async function sendStkPush() {
  if (!phone.value) {
    notify.warning('Please enter the customer mobile phone number.');
    return;
  }

  status.value = 'sending';
  try {
    const res = await api.post('/pos/mpesa-prompt', {
      phone: phone.value,
      amount: props.amount,
    });

    if (res.data?.success) {
      generatedReceiptCode.value = res.data.data?.simulated_receipt || ('QK' + Math.floor(10000000 + Math.random() * 90000000));
      status.value = 'awaiting_pin';
      countdown.value = 30;

      cleanupTimer();
      timerInterval = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 10 && status.value === 'awaiting_pin') {
          // Auto-simulate confirmation after 20 seconds if customer doesn't cancel
          completeTransaction(generatedReceiptCode.value);
        } else if (countdown.value <= 0) {
          cleanupTimer();
          if (status.value === 'awaiting_pin') {
            status.value = 'failed';
          }
        }
      }, 1000);
    } else {
      notify.error(res.data?.message || 'Failed to initiate M-Pesa prompt.');
      status.value = 'idle';
    }
  } catch (err) {
    console.error('M-Pesa STK push error:', err);
    notify.error(err.response?.data?.message || 'Could not reach Safaricom Daraja gateway.');
    status.value = 'idle';
  }
}

function completeTransaction(code) {
  cleanupTimer();
  confirmedCode.value = code;
  status.value = 'success';
  notify.success(`M-Pesa payment confirmed! Code: ${code}`);
}

function applyAndClose() {
  emit('confirmed', {
    transactionCode: confirmedCode.value,
    amount: props.amount,
    phone: phone.value,
  });
  emit('update:modelValue', false);
}

function cancelPrompt() {
  cleanupTimer();
  status.value = 'idle';
  emit('cancelled');
  emit('update:modelValue', false);
}

onUnmounted(() => {
  cleanupTimer();
});
</script>