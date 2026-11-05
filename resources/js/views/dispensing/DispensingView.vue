<template>
  <div>
    <!-- HEADER -->
    <v-card class="elevation-1 rounded-lg pa-4 mb-4" color="white">
      <div class="d-flex flex-wrap justify-space-between align-center ga-3">
        <div class="d-flex align-center">
          <v-avatar color="blue-darken-2" class="mr-3" size="44">
            <v-icon icon="mdi-clipboard-plus-outline" color="white" size="24"></v-icon>
          </v-avatar>
          <div>
            <h1 class="text-h6 font-weight-bold">Clinical Medicine Dispensing & Prescription Registry</h1>
            <p class="text-caption text-grey">Patient profiles, prescription validation, and complete dispensing history</p>
          </div>
        </div>

        <div class="d-flex ga-2">
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            @click="showAddRxModal = true"
            class="text-none font-weight-medium"
          >
            Register Prescription
          </v-btn>
        </div>
      </div>
    </v-card>

    <!-- TABS -->
    <v-tabs v-model="activeTab" color="primary" class="mb-4 bg-white rounded-lg elevation-1 px-2">
      <v-tab value="prescriptions" class="text-none">
        <v-icon start icon="mdi-file-document-outline"></v-icon>
        Prescriptions Registry
      </v-tab>
      <v-tab value="history" class="text-none">
        <v-icon start icon="mdi-history"></v-icon>
        Dispensation Audit Trail
      </v-tab>
      <v-tab value="patient-profile" class="text-none">
        <v-icon start icon="mdi-account-clock-outline"></v-icon>
        Patient Medication Timeline
      </v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <!-- TAB 1: PRESCRIPTIONS REGISTRY -->
      <v-window-item value="prescriptions">
        <v-card class="elevation-1 rounded-lg pa-4">
          <v-row density="compact" class="mb-2" align="center">
            <v-col cols="12" sm="5">
              <v-text-field
                v-model="rxSearch"
                label="Search by Patient Name, Rx #, or Doctor"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @update:model-value="onRxSearch"
                @click:clear="loadPrescriptions"
              ></v-text-field>
            </v-col>
            <v-col cols="12" sm="4">
              <v-select
                v-model="selectedStatus"
                :items="[
                  { title: 'All Statuses', value: null },
                  { title: 'Pending Only', value: 'pending' },
                  { title: 'Partially Dispensed', value: 'partially_dispensed' },
                  { title: 'Fully Dispensed', value: 'fully_dispensed' },
                ]"
                item-title="title"
                item-value="value"
                label="Filter Status"
                variant="outlined"
                density="compact"
                hide-details
                @update:model-value="loadPrescriptions"
              ></v-select>
            </v-col>
            <v-col cols="12" sm="3" class="text-end">
              <v-btn icon="mdi-refresh" variant="text" @click="loadPrescriptions" :loading="loadingRx"></v-btn>
            </v-col>
          </v-row>

          <v-table hover density="comfortable">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th class="font-weight-bold">Rx Number</th>
                <th class="font-weight-bold">Patient Name</th>
                <th class="font-weight-bold">Prescriber & Facility</th>
                <th class="font-weight-bold">Date & Time</th>
                <th class="font-weight-bold">Medicines</th>
                <th class="font-weight-bold text-center">Status</th>
                <th class="font-weight-bold text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="prescriptions.length === 0">
                <td colspan="7" class="text-center text-grey py-6">No prescriptions found.</td>
              </tr>
              <tr v-for="rx in prescriptions" :key="rx.id">
                <td class="font-mono font-weight-bold text-caption text-primary" :title="'Rx Number: ' + rx.prescription_number">
                  {{ rx.prescription_number }}
                </td>
                <td class="font-weight-medium" :title="'Patient: ' + rx.patient_name + (rx.customer?.phone ? ' | Tel: ' + rx.customer.phone : '')">
                  {{ rx.patient_name }}
                  <div v-if="rx.customer" class="text-caption text-grey">Reg: {{ rx.customer.phone || 'Customer' }}</div>
                </td>
                <td :title="'Prescribing Doctor: ' + rx.prescriber_name + ' at ' + (rx.prescriber_facility || 'Clinic')">
                  <div class="font-weight-medium text-caption">{{ rx.prescriber_name }}</div>
                  <div class="text-caption text-grey">{{ rx.prescriber_facility || 'Clinic' }}</div>
                </td>
                <td class="text-caption font-mono" :title="'Prescription Date: ' + (rx.prescription_date || '')">
                  {{ formatIsoDateTime(rx.created_at || rx.prescription_date) }}
                </td>
                <td>
                  <div v-for="it in rx.items" :key="it.id" class="text-caption" :title="it.product?.name + ': ' + Number(it.quantity_dispensed) + ' of ' + Number(it.quantity_prescribed) + ' dispensed'">
                    • <b>{{ it.product?.name }}</b> ({{ Number(it.quantity_dispensed) }}/{{ Number(it.quantity_prescribed) }})
                  </div>
                </td>
                <td class="text-center">
                  <v-chip
                    size="small"
                    :color="getRxStatusColor(rx.status)"
                    variant="flat"
                    class="font-weight-bold text-capitalize text-no-wrap"
                    :title="'Dispensing Status: ' + rx.status.replace('_', ' ')"
                  >
                    {{ rx.status.replace('_', ' ') }}
                  </v-chip>
                </td>
                <td class="text-end">
                  <v-btn
                    v-if="rx.status !== 'fully_dispensed' && rx.status !== 'cancelled'"
                    color="primary"
                    size="small"
                    variant="flat"
                    prepend-icon="mdi-pill"
                    @click="openDispenseModal(rx)"
                    class="text-none mr-1"
                  >
                    Dispense
                  </v-btn>
                  <v-btn
                    icon="mdi-eye-outline"
                    size="small"
                    variant="text"
                    @click="viewRxDetails(rx)"
                    title="View Clinical Details"
                  ></v-btn>
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <!-- TAB 2: DISPENSATION LOGS -->
      <v-window-item value="history">
        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="d-flex justify-space-between align-center mb-3">
            <div class="text-subtitle-1 font-weight-bold text-grey-darken-3">
              Official Pharmacy Dispensation Records
            </div>
            <v-btn icon="mdi-refresh" variant="text" size="small" @click="loadDispensations" :loading="loadingDispensations"></v-btn>
          </div>

          <v-table hover density="comfortable">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th class="font-weight-bold">Dispensation Date & Time</th>
                <th class="font-weight-bold">Patient</th>
                <th class="font-weight-bold">Dispensing Pharmacist</th>
                <th class="font-weight-bold">Medicines Dispensed</th>
                <th class="font-weight-bold">Clinical Instructions</th>
                <th class="font-weight-bold text-center">Receipt #</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="dispensations.length === 0">
                <td colspan="6" class="text-center text-grey py-6">No dispensations logged yet.</td>
              </tr>
              <tr v-for="d in dispensations" :key="d.id">
                <td class="text-caption font-mono">{{ formatIsoDateTime(d.dispensation_date || d.created_at) }}</td>
                <td class="font-weight-medium">{{ d.patient_name }}</td>
                <td class="text-caption">{{ d.dispenser?.name }}</td>
                <td>
                  <div v-for="di in d.items" :key="di.id" class="text-caption">
                    <b>{{ di.product?.name }}</b> ({{ Number(di.quantity_dispensed) }} units | Batch: {{ di.batch_number }})
                  </div>
                </td>
                <td class="text-caption text-grey-darken-2">
                  <div v-for="di in d.items" :key="di.id">
                    {{ di.dosage_instructions || d.pharmacist_notes || 'As prescribed' }}
                  </div>
                </td>
                <td class="text-center font-mono text-caption">
                  {{ d.sale?.receipt_number || 'N/A' }}
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <!-- TAB 3: PATIENT MEDICATION TIMELINE -->
      <v-window-item value="patient-profile">
        <v-card class="elevation-1 rounded-lg pa-4">
          <v-row density="compact" class="mb-3">
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="patientSearchName"
                label="Enter Patient Name to View Complete History"
                prepend-inner-icon="mdi-account-search"
                variant="outlined"
                density="compact"
                hide-details
                @keydown.enter="searchPatientHistory"
              >
                <template v-slot:append>
                  <v-btn color="primary" @click="searchPatientHistory" :loading="loadingPatientHistory">Search</v-btn>
                </template>
              </v-text-field>
            </v-col>
          </v-row>

          <div v-if="patientHistoryList.length > 0">
            <v-timeline density="compact" align="start">
              <v-timeline-item
                v-for="h in patientHistoryList"
                :key="h.id"
                dot-color="primary"
                size="small"
              >
                <v-card class="elevation-1 rounded pa-3 mb-2">
                  <div class="d-flex justify-space-between align-center">
                    <span class="font-weight-bold text-subtitle-2">{{ h.patient_name }}</span>
                    <span class="text-caption font-mono text-grey">{{ formatIsoDateTime(h.dispensation_date || h.created_at) }}</span>
                  </div>
                  <div class="text-caption text-grey mb-2">Dispensed by: {{ h.dispenser?.name }}</div>

                  <v-divider class="my-1"></v-divider>

                  <div v-for="item in h.items" :key="item.id" class="my-1">
                    <span class="font-weight-medium text-body-2">• {{ item.product?.name }}</span>
                    <span class="text-caption text-grey ml-1">({{ Number(item.quantity_dispensed) }} units)</span>
                    <div class="text-caption text-teal-darken-4 font-italic pl-3">
                      Dosage: {{ item.dosage_instructions || 'Take as directed' }}
                    </div>
                  </div>
                </v-card>
              </v-timeline-item>
            </v-timeline>
          </div>
          <div v-else-if="searchedPatient" class="text-center py-8 text-grey">
            No previous dispensing records found for "{{ searchedPatient }}"
          </div>
        </v-card>
      </v-window-item>
    </v-window>

    <!-- MODAL 1: REGISTER PRESCRIPTION -->
    <v-dialog v-model="showAddRxModal" max-width="700px" persistent>
      <v-card class="pa-4 rounded-lg">
        <v-card-title class="font-weight-bold text-h6 d-flex justify-space-between align-center pb-2 border-b">
          <span>Register Doctor's Prescription</span>
          <v-btn
            icon="mdi-close"
            variant="text"
            size="small"
            color="grey-darken-2"
            @click="showAddRxModal = false"
            title="Close"
          ></v-btn>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-form @submit.prevent="savePrescription">
            <v-row density="compact">
              <v-col cols="12" sm="6">
                <v-text-field v-model="newRx.patient_name" label="Patient Full Name *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="newRx.prescription_number" label="Prescription / Rx Number" placeholder="Leave empty for auto" variant="outlined" density="compact"></v-text-field>
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="newRx.prescriber_name" label="Doctor / Clinician Name *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="newRx.prescriber_facility" label="Hospital / Medical Facility" variant="outlined" density="compact"></v-text-field>
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="newRx.prescriber_license" label="KMPDC Doctor License #" variant="outlined" density="compact"></v-text-field>
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="newRx.prescription_date" type="date" label="Prescription Date *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12">
                <v-textarea v-model="newRx.diagnosis_notes" label="Diagnosis & Clinical Notes" variant="outlined" density="compact" rows="2"></v-textarea>
              </v-col>
            </v-row>

            <div class="text-subtitle-2 font-weight-bold mt-3 mb-2 text-primary">Prescribed Medicines:</div>
            <div v-for="(item, idx) in newRx.items" :key="idx" class="pa-3 mb-2 bg-grey-lighten-5 border rounded">
              <v-row density="compact" align="center">
                <v-col cols="12" sm="5">
                  <v-select
                    v-model="item.product_id"
                    :items="products"
                    item-title="name"
                    item-value="id"
                    label="Medicine *"
                    variant="outlined"
                    density="compact"
                    required
                  ></v-select>
                </v-col>
                <v-col cols="12" sm="4">
                  <v-text-field v-model="item.dosage_instruction" label="Dosage (e.g. 1 tab BD)" variant="outlined" density="compact" required></v-text-field>
                </v-col>
                <v-col cols="6" sm="2">
                  <v-text-field v-model.number="item.quantity_prescribed" type="number" label="Total Qty *" variant="outlined" density="compact" required></v-text-field>
                </v-col>
                <v-col cols="6" sm="1" class="text-center">
                  <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="newRx.items.splice(idx, 1)" :disabled="newRx.items.length <= 1"></v-btn>
                </v-col>
              </v-row>
            </div>

            <v-btn size="small" variant="text" color="primary" prepend-icon="mdi-plus" @click="addRxItemRow" class="text-none mb-3">
              Add Another Medicine
            </v-btn>

            <div class="d-flex justify-end ga-2 pt-2 border-t">
              <v-btn color="grey-darken-2" variant="outlined" class="text-none" @click="showAddRxModal = false">Cancel</v-btn>
              <v-btn color="primary" type="submit" :loading="savingRx" class="text-none font-weight-bold">Save Prescription</v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL 2: DISPENSE MEDICINE DIALOG -->
    <v-dialog v-model="showDispenseModal" max-width="750px" persistent>
      <v-card class="pa-4 rounded-lg">
        <v-card-title class="font-weight-bold text-h6 d-flex justify-space-between align-center pb-2 border-b">
          <div class="d-flex align-center">
            <span>Clinical Dispensing Desk</span>
            <v-chip color="primary" variant="flat" size="small" class="ml-2 font-mono">{{ activeDispenseRx?.prescription_number }}</v-chip>
          </div>
          <v-btn
            icon="mdi-close"
            variant="text"
            size="small"
            color="grey-darken-2"
            @click="showDispenseModal = false"
            title="Close"
          ></v-btn>
        </v-card-title>

        <v-card-text class="pt-3">
          <div class="bg-blue-lighten-5 pa-3 rounded mb-3 text-caption">
            <div><b>Patient:</b> {{ activeDispenseRx?.patient_name }}</div>
            <div><b>Prescriber:</b> {{ activeDispenseRx?.prescriber_name }} ({{ activeDispenseRx?.prescriber_facility }})</div>
            <div v-if="activeDispenseRx?.diagnosis_notes"><b>Diagnosis:</b> {{ activeDispenseRx?.diagnosis_notes }}</div>
          </div>

          <div class="text-subtitle-2 font-weight-bold mb-2">Medicines to Dispense:</div>
          <v-table density="compact" class="border rounded mb-3">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th>Medicine</th>
                <th>Prescribed</th>
                <th>Remaining</th>
                <th style="width: 120px;">Dispense Qty</th>
                <th class="text-end">Price / Unit</th>
                <th class="text-end">Line Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="it in dispenseItems" :key="it.product_id">
                <td class="font-weight-medium">
                  {{ it.name }}
                  <div class="text-caption text-teal-darken-3 font-italic">{{ it.dosage_instruction }}</div>
                </td>
                <td>{{ it.quantity_prescribed }}</td>
                <td class="font-weight-bold">{{ it.remaining_quantity }}</td>
                <td>
                  <v-text-field
                    v-model.number="it.quantity_to_dispense"
                    type="number"
                    min="0"
                    :max="it.remaining_quantity"
                    variant="outlined"
                    density="compact"
                    hide-details
                  ></v-text-field>
                </td>
                <td class="text-end font-mono">KES {{ it.unit_selling_price.toFixed(2) }}</td>
                <td class="text-end font-mono font-weight-bold">
                  KES {{ (it.quantity_to_dispense * it.unit_selling_price).toFixed(2) }}
                </td>
              </tr>
            </tbody>
          </v-table>

          <v-row density="compact" align="center">
            <v-col cols="12" sm="6">
              <v-select
                v-model="dispensePaymentMethod"
                :items="['cash', 'mpesa', 'card', 'credit']"
                label="Payment Method *"
                variant="outlined"
                density="compact"
                hide-details
              ></v-select>
            </v-col>
            <v-col cols="12" sm="6" class="text-end">
              <div class="text-caption text-grey">Total Settle:</div>
              <div class="text-h6 font-weight-bold text-teal-darken-3 font-mono">
                KES {{ dispenseGrandTotal.toFixed(2) }}
              </div>
            </v-col>
          </v-row>
        </v-card-text>

        <v-card-actions class="pa-4 border-t">
          <v-btn color="grey-darken-2" variant="outlined" class="text-none" @click="showDispenseModal = false">Cancel</v-btn>
          <v-spacer></v-spacer>
          <v-btn
            color="success"
            variant="flat"
            size="large"
            :loading="processingDispense"
            :disabled="dispenseGrandTotal <= 0"
            @click="submitDispense"
            class="px-6 text-none font-weight-bold"
          >
            Authorize & Complete Dispense
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/services/api';
import { useNotificationStore } from '@/stores/notification';
import { formatIsoDateTime } from '@/utils/formatters';

const notify = useNotificationStore();

const activeTab = ref('prescriptions');
const rxSearch = ref('');
const selectedStatus = ref(null);
const loadingRx = ref(false);
const prescriptions = ref([]);
const products = ref([]);

// Dispensation Audit
const dispensations = ref([]);
const loadingDispensations = ref(false);

// Patient Timeline
const patientSearchName = ref('');
const searchedPatient = ref('');
const patientHistoryList = ref([]);
const loadingPatientHistory = ref(false);

// New Prescription Modal
const showAddRxModal = ref(false);
const savingRx = ref(false);
const newRx = ref({
  patient_name: '',
  prescription_number: '',
  prescriber_name: '',
  prescriber_facility: '',
  prescriber_license: '',
  prescription_date: new Date().toISOString().substring(0, 10),
  diagnosis_notes: '',
  items: [
    { product_id: null, dosage_instruction: '1 tablet twice daily', frequency: 'BD', duration_days: 5, quantity_prescribed: 10 }
  ],
});

// Dispense Modal
const showDispenseModal = ref(false);
const processingDispense = ref(false);
const activeDispenseRx = ref(null);
const dispenseItems = ref([]);
const dispensePaymentMethod = ref('cash');

const dispenseGrandTotal = computed(() => {
  return dispenseItems.value.reduce((sum, it) => sum + (it.quantity_to_dispense * it.unit_selling_price), 0);
});

function getRxStatusColor(status) {
  if (status === 'fully_dispensed') return 'success';
  if (status === 'partially_dispensed') return 'amber-darken-3';
  if (status === 'cancelled') return 'error';
  return 'info';
}

let rxDebounceTimer = null;
function onRxSearch() {
  clearTimeout(rxDebounceTimer);
  rxDebounceTimer = setTimeout(() => {
    loadPrescriptions();
  }, 300);
}

async function loadPrescriptions() {
  loadingRx.value = true;
  try {
    const params = {};
    if (rxSearch.value) params.search = rxSearch.value;
    if (selectedStatus.value) params.status = selectedStatus.value;

    const res = await api.get('/prescriptions', { params });
    prescriptions.value = res.data.data.data;
  } catch (e) {
    notify.error('Failed to load prescriptions');
  } finally {
    loadingRx.value = false;
  }
}

async function loadProducts() {
  try {
    const res = await api.get('/products', { params: { per_page: 100 } });
    products.value = res.data.data.data;
  } catch (e) {}
}

async function loadDispensations() {
  loadingDispensations.value = true;
  try {
    const res = await api.get('/dispensations');
    dispensations.value = res.data.data.data;
  } catch (e) {
    notify.error('Failed to load dispensation history');
  } finally {
    loadingDispensations.value = false;
  }
}

async function searchPatientHistory() {
  if (!patientSearchName.value.trim()) return;
  loadingPatientHistory.value = true;
  searchedPatient.value = patientSearchName.value;
  try {
    const res = await api.get('/dispensations/patient-history', {
      params: { patient_name: patientSearchName.value }
    });
    patientHistoryList.value = res.data.data;
  } catch (e) {
    notify.error('Failed to load patient profile');
  } finally {
    loadingPatientHistory.value = false;
  }
}

function addRxItemRow() {
  newRx.value.items.push({
    product_id: null,
    dosage_instruction: '',
    frequency: 'BD',
    duration_days: 5,
    quantity_prescribed: 10,
  });
}

async function savePrescription() {
  savingRx.value = true;
  try {
    await api.post('/prescriptions', newRx.value);
    notify.success('Prescription registered successfully.');
    showAddRxModal.value = false;
    loadPrescriptions();
    newRx.value = {
      patient_name: '',
      prescription_number: '',
      prescriber_name: '',
      prescriber_facility: '',
      prescriber_license: '',
      prescription_date: new Date().toISOString().substring(0, 10),
      diagnosis_notes: '',
      items: [{ product_id: null, dosage_instruction: '1 tablet twice daily', frequency: 'BD', duration_days: 5, quantity_prescribed: 10 }],
    };
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to save prescription');
  } finally {
    savingRx.value = false;
  }
}

function openDispenseModal(rx) {
  activeDispenseRx.value = rx;
  dispenseItems.value = rx.items.map(it => ({
    prescription_item_id: it.id,
    product_id: it.product_id,
    name: it.product?.name,
    dosage_instruction: it.dosage_instruction,
    quantity_prescribed: Number(it.quantity_prescribed),
    remaining_quantity: Number(it.remaining_quantity),
    quantity_to_dispense: Number(it.remaining_quantity),
    unit_selling_price: Number(it.product?.selling_price || 0),
  }));
  showDispenseModal.value = true;
}

async function submitDispense() {
  processingDispense.value = true;
  try {
    const itemsToDispense = dispenseItems.value
      .filter(it => it.quantity_to_dispense > 0)
      .map(it => ({
        product_id: it.product_id,
        quantity: it.quantity_to_dispense,
        unit_selling_price: it.unit_selling_price,
      }));

    const payload = {
      prescription_id: activeDispenseRx.value.id,
      customer_id: activeDispenseRx.value.customer_id,
      pharmacist_notes: 'Dispensed through clinical desk',
      items: itemsToDispense,
      payments: [
        {
          payment_method: dispensePaymentMethod.value,
          amount: dispenseGrandTotal.value,
        }
      ],
    };

    await api.post('/dispensations', payload);
    notify.success('Medicine dispensed and deducted from inventory via FEFO.');
    showDispenseModal.value = false;
    loadPrescriptions();
    loadDispensations();
  } catch (e) {
    notify.error(e.response?.data?.message || 'Dispensing failed. Check stock availability.');
  } finally {
    processingDispense.value = false;
  }
}

function viewRxDetails(rx) {
  openDispenseModal(rx);
}

onMounted(() => {
  loadPrescriptions();
  loadProducts();
  loadDispensations();
});
</script>
