<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="420px"
    persistent
  >
    <v-card class="pa-4 rounded-lg">
      <div class="d-flex justify-space-between align-center pb-2 border-b">
        <span class="text-subtitle-1 font-weight-bold text-teal-darken-4">Sales Receipt Ready</span>
        <v-btn
          icon="mdi-close"
          variant="text"
          size="small"
          color="grey-darken-2"
          @click="$emit('update:modelValue', false)"
          title="Close Receipt"
        ></v-btn>
      </div>

      <v-card-text class="pt-3 px-1">
        <!-- Thermal Receipt Visual Preview Box -->
        <div id="receipt-preview-box" class="receipt-box pa-3 bg-white border rounded elevation-0">
          <div class="text-center mb-2">
            <h3 class="text-subtitle-1 font-weight-bold text-uppercase">{{ companyName }}</h3>
            <div class="text-caption text-grey-darken-2">{{ branchName }}</div>
            <div class="text-caption text-grey font-mono">{{ phoneOrTaxPin }}</div>
          </div>

          <div class="border-t-dashed my-2"></div>

          <div class="font-mono text-caption text-grey-darken-3 mb-2">
            <div><strong>Receipt #:</strong> {{ sale?.receipt_number }}</div>
            <div><strong>Date & Time:</strong> {{ formattedDate }}</div>
            <div><strong>Cashier:</strong> {{ sale?.cashier?.name || 'Cashier Desk' }}</div>
            <div><strong>Customer:</strong> {{ sale?.customer?.name || 'Walk-in Customer' }}</div>
          </div>

          <div class="border-t-dashed my-2"></div>

          <!-- Items Table -->
          <table class="w-100 font-mono text-caption mb-2">
            <thead>
              <tr class="border-b">
                <th class="text-start py-1">Item</th>
                <th class="text-center py-1">Qty</th>
                <th class="text-end py-1">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in (sale?.items || [])" :key="item.id">
                <td class="py-1">{{ item.product?.name }}</td>
                <td class="text-center py-1">{{ Number(item.quantity) }}</td>
                <td class="text-end font-weight-bold py-1">KES {{ Number(item.subtotal).toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>

          <div class="border-t-dashed my-2"></div>

          <div class="d-flex justify-space-between font-weight-bold font-mono text-subtitle-2 mb-1">
            <span>TOTAL:</span>
            <span class="text-teal-darken-4">KES {{ Number(sale?.total_amount || 0).toFixed(2) }}</span>
          </div>

          <div class="border-t-dashed my-2"></div>

          <div class="font-bold text-caption text-grey-darken-2 mb-1">Payment Method:</div>
          <div v-for="p in (sale?.payments || [])" :key="p.id" class="d-flex justify-space-between font-mono text-caption">
            <span class="text-uppercase">{{ p.payment_method }}:</span>
            <span>KES {{ Number(p.amount).toFixed(2) }}</span>
          </div>

          <div class="border-t-dashed my-2"></div>
          <div class="text-center text-caption text-grey font-italic">
            Thank you for choosing Afya Meds Pharmacy.<br>Get well soon!
          </div>
        </div>
      </v-card-text>

      <v-card-actions class="border-t pt-3 ga-2">
        <v-btn
          variant="outlined"
          color="teal-darken-3"
          prepend-icon="mdi-printer"
          @click="printThermalReceipt"
          class="flex-grow-1 text-none font-weight-medium"
        >
          Print Thermal
        </v-btn>
        <v-btn
          variant="tonal"
          color="purple-darken-2"
          prepend-icon="mdi-file-pdf-box"
          :loading="downloadingPdf"
          @click="downloadReceiptPdf"
          class="flex-grow-1 text-none font-weight-medium"
        >
          PDF Receipt
        </v-btn>
        <v-btn
          color="success"
          variant="flat"
          @click="$emit('new-sale')"
          class="flex-grow-1 text-none font-weight-bold"
        >
          New Sale
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed } from 'vue';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useNotificationStore } from '@/stores/notification';
import { formatDateTime } from '@/utils/formatters';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  sale: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['update:modelValue', 'new-sale']);

const authStore = useAuthStore();
const notify = useNotificationStore();
const downloadingPdf = ref(false);

const companyName = computed(() => {
  return authStore.user?.pharmacy?.name || 'Afya Meds Pharmacy Ltd';
});

const branchName = computed(() => {
  return authStore.branch?.name || 'Main Branch';
});

const phoneOrTaxPin = computed(() => {
  return 'Tel: +254 700 000 000 | KRA PIN Configured';
});

const formattedDate = computed(() => {
  if (!props.sale?.created_at && !props.sale?.sale_date) {
    return formatDateTime(new Date());
  }
  return formatDateTime(props.sale.created_at || props.sale.sale_date);
});

function printThermalReceipt() {
  if (!props.sale) return;

  const printWindow = window.open('', '_blank', 'width=380,height=600');
  if (!printWindow) {
    notify.error('Please allow popups to print receipts directly.');
    return;
  }

  const itemsHtml = (props.sale.items || [])
    .map(
      (item) => `
      <tr>
        <td style="padding: 3px 0; text-align: left;">${item.product?.name || 'Medicine'}</td>
        <td style="padding: 3px 0; text-align: center;">${Number(item.quantity)}</td>
        <td style="padding: 3px 0; text-align: right; font-weight: bold;">${Number(item.subtotal).toFixed(2)}</td>
      </tr>
    `
    )
    .join('');

  const paymentsHtml = (props.sale.payments || [])
    .map(
      (p) => `
      <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 2px;">
        <span style="text-transform: uppercase;">${p.payment_method}:</span>
        <span>KES ${Number(p.amount).toFixed(2)}</span>
      </div>
    `
    )
    .join('');

  const receiptHtml = `
    <!DOCTYPE html>
    <html>
      <head>
        <title>Receipt ${props.sale.receipt_number}</title>
        <style>
          @page { size: 80mm auto; margin: 0; }
          body {
            font-family: 'Courier New', Courier, monospace;
            width: 76mm;
            margin: 0 auto;
            padding: 10px 4px;
            color: #000;
            background: #fff;
            font-size: 12px;
            line-height: 1.3;
          }
          .text-center { text-align: center; }
          .font-bold { font-weight: bold; }
          .divider { border-top: 1px dashed #000; margin: 6px 0; }
          table { width: 100%; border-collapse: collapse; font-size: 11px; }
          @media print {
            body { width: 100%; padding: 0; }
          }
        </style>
      </head>
      <body>
        <div class="text-center font-bold" style="font-size: 14px;">${companyName.value}</div>
        <div class="text-center" style="font-size: 11px;">${branchName.value}</div>
        <div class="text-center" style="font-size: 10px;">${phoneOrTaxPin.value}</div>
        <div class="divider"></div>
        <div style="font-size: 11px;">
          <div><strong>Receipt #:</strong> ${props.sale.receipt_number}</div>
          <div><strong>Date:</strong> ${formattedDate.value}</div>
          <div><strong>Cashier:</strong> ${props.sale.cashier?.name || 'Cashier'}</div>
          <div><strong>Customer:</strong> ${props.sale.customer?.name || 'Walk-in'}</div>
        </div>
        <div class="divider"></div>
        <table>
          <thead>
            <tr style="border-bottom: 1px solid #000;">
              <th style="text-align: left;">Item</th>
              <th style="text-align: center;">Qty</th>
              <th style="text-align: right;">Total</th>
            </tr>
          </thead>
          <tbody>
            ${itemsHtml}
          </tbody>
        </table>
        <div class="divider"></div>
        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 13px;">
          <span>TOTAL:</span>
          <span>KES ${Number(props.sale.total_amount).toFixed(2)}</span>
        </div>
        <div class="divider"></div>
        <div style="font-size: 11px; font-weight: bold; margin-bottom: 2px;">Payment:</div>
        ${paymentsHtml}
        <div class="divider"></div>
        <div class="text-center" style="font-size: 10px; margin-top: 8px;">
          Thank you for choosing Afya Meds Pharmacy.<br/>Get well soon!
        </div>
        <script>
          window.onload = function() {
            window.print();
            setTimeout(function() { window.close(); }, 500);
          };
        <\/script>
      </body>
    </html>
  `;

  printWindow.document.open();
  printWindow.document.write(receiptHtml);
  printWindow.document.close();
}

async function downloadReceiptPdf() {
  if (!props.sale?.id) return;
  downloadingPdf.value = true;
  try {
    const res = await api.get(`/pos/receipt/${props.sale.id}/pdf`, {
      responseType: 'blob',
    });
    const blob = new Blob([res.data], { type: 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `Receipt-${props.sale.receipt_number || props.sale.id}.pdf`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    notify.success('PDF Receipt downloaded successfully.');
  } catch (err) {
    console.error('PDF download error:', err);
    notify.error('Failed to generate PDF receipt.');
  } finally {
    downloadingPdf.value = false;
  }
}
</script>

<style scoped>
.receipt-box {
  border: 1px solid #e0e0e0;
  max-height: 400px;
  overflow-y: auto;
}
.border-t-dashed {
  border-top: 1px dashed #9e9e9e;
}
</style>