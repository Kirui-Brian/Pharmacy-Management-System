<template>
  <div>
    <!-- HEADER -->
    <v-card class="elevation-1 rounded-lg pa-4 mb-4" color="white">
      <div class="d-flex flex-wrap justify-space-between align-center ga-3">
        <div class="d-flex align-center">
          <v-avatar color="indigo-darken-1" class="mr-3" size="44">
            <v-icon icon="mdi-truck-delivery-outline" color="white" size="24"></v-icon>
          </v-avatar>
          <div>
            <h1 class="text-h6 font-weight-bold">Procurement, Stock Ingestion & Supplier Management</h1>
            <p class="text-caption text-grey">Purchase orders, GRN stock intake, supplier invoices, and payment tracking</p>
          </div>
        </div>

        <div class="d-flex flex-wrap ga-2">
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            @click="openCreatePoDialog"
            class="text-none font-weight-medium"
          >
            New Purchase Order
          </v-btn>
          <v-btn
            color="success"
            variant="flat"
            prepend-icon="mdi-package-down"
            @click="openReceiveGrnDialog()"
            class="text-none font-weight-medium"
          >
            Receive Stock (GRN)
          </v-btn>
          <v-btn
            color="deep-purple-darken-1"
            variant="tonal"
            prepend-icon="mdi-cash-fast"
            @click="openPaymentDialog()"
            class="text-none font-weight-medium"
          >
            Pay Supplier
          </v-btn>
          <v-btn
            color="error"
            variant="outlined"
            prepend-icon="mdi-arrow-u-left-top"
            @click="openReturnDialog"
            class="text-none font-weight-medium"
          >
            Return to Supplier
          </v-btn>
        </div>
      </div>
    </v-card>

    <!-- METRICS CARDS (Uniform height & structure) -->
    <v-row density="compact" class="mb-4">
      <v-col cols="12" sm="6" md="3">
        <v-card
          class="elevation-1 rounded-lg pa-4 border-s-lg border-blue h-100 d-flex flex-column justify-center cursor-default"
          style="min-height: 104px;"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <p class="text-caption text-blue-darken-3 font-weight-bold mb-1">OPEN PURCHASE ORDERS</p>
              <h3 class="text-h6 font-weight-bold text-blue-darken-4">{{ openOrdersCount }}</h3>
              <p class="text-caption text-grey-darken-1">Value: KES {{ formatNumber(openOrdersTotal) }}</p>
            </div>
            <v-avatar color="blue-lighten-5" size="44">
              <v-icon icon="mdi-clock-outline" color="blue-darken-2" size="24"></v-icon>
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
              <p class="text-caption text-teal-darken-3 font-weight-bold mb-1">STOCK RECEIVED (GRNS)</p>
              <h3 class="text-h6 font-weight-bold text-teal-darken-4">{{ grnsCount }}</h3>
              <p class="text-caption text-grey-darken-1">Total: KES {{ formatNumber(grnsTotalValue) }}</p>
            </div>
            <v-avatar color="teal-lighten-5" size="44">
              <v-icon icon="mdi-truck-check-outline" color="teal-darken-2" size="24"></v-icon>
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
              <p class="text-caption text-amber-darken-4 font-weight-bold mb-1">ACCOUNTS PAYABLE</p>
              <h3 class="text-h6 font-weight-bold text-amber-darken-4">KES {{ formatNumber(totalPayable) }}</h3>
              <p class="text-caption text-grey-darken-1">Active Suppliers: {{ suppliers.length }}</p>
            </div>
            <v-avatar color="amber-lighten-5" size="44">
              <v-icon icon="mdi-currency-usd" color="amber-darken-3" size="24"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card
          class="elevation-1 rounded-lg pa-4 border-s-lg border-red h-100 d-flex flex-column justify-center cursor-default"
          style="min-height: 104px;"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <p class="text-caption text-red-darken-3 font-weight-bold mb-1">OVERDUE INVOICES</p>
              <h3 class="text-h6 font-weight-bold text-red-darken-4">{{ overdueCount }}</h3>
              <p class="text-caption text-grey-darken-1">Due: KES {{ formatNumber(overdueTotal) }}</p>
            </div>
            <v-avatar color="red-lighten-5" size="44">
              <v-icon icon="mdi-alert-circle-outline" color="red-darken-2" size="24"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- TABS -->
    <v-tabs v-model="activeTab" color="primary" class="mb-4 bg-white rounded-lg elevation-1 px-2">
      <v-tab value="orders" class="text-none">
        <v-icon start icon="mdi-clipboard-list-outline"></v-icon>
        Purchase Orders ({{ orders.length }})
      </v-tab>
      <v-tab value="grn" class="text-none">
        <v-icon start icon="mdi-package-variant-closed"></v-icon>
        Goods Received Notes ({{ grns.length }})
      </v-tab>
      <v-tab value="invoices" class="text-none">
        <v-icon start icon="mdi-file-percent-outline"></v-icon>
        Suppliers & Bills Payable
      </v-tab>
      <v-tab value="returns" class="text-none">
        <v-icon start icon="mdi-undo-variant"></v-icon>
        Supplier Returns ({{ purchaseReturns.length }})
      </v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <!-- ========================================== -->
      <!-- TAB 1: PURCHASE ORDERS                     -->
      <!-- ========================================== -->
      <v-window-item value="orders">
        <v-card class="elevation-1 rounded-lg pa-4">
          <v-row density="compact" class="mb-2" align="center">
            <v-col cols="12" sm="5">
              <v-text-field
                v-model="orderSearch"
                label="Search PO # or Supplier..."
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @update:model-value="onOrderSearchDebounced"
                @click:clear="loadOrders"
              ></v-text-field>
            </v-col>

            <v-col cols="12" sm="4">
              <v-select
                v-model="orderStatusFilter"
                :items="orderStatusOptions"
                label="Filter by Status"
                variant="outlined"
                density="compact"
                hide-details
                @update:model-value="loadOrders"
              ></v-select>
            </v-col>

            <v-col cols="12" sm="3" class="d-flex justify-end">
              <v-btn
                prepend-icon="mdi-refresh"
                variant="tonal"
                density="compact"
                @click="loadOrders"
                class="text-none"
              >
                Refresh
              </v-btn>
            </v-col>
          </v-row>

          <v-data-table
            :headers="orderHeaders"
            :items="orders"
            :loading="loadingOrders"
            density="comfortable"
            hover
            class="elevation-0"
          >
            <template #[`item.po_number`]="{ item }">
              <span class="font-weight-bold text-primary">{{ item.po_number }}</span>
            </template>

            <template #[`item.supplier`]="{ item }">
              <div class="font-weight-medium">{{ item.supplier?.name || 'N/A' }}</div>
              <div class="text-caption text-grey">{{ item.supplier?.phone }}</div>
            </template>

            <template #[`item.order_date`]="{ item }">
              {{ formatDate(item.order_date) }}
            </template>

            <template #[`item.expected_delivery_date`]="{ item }">
              {{ item.expected_delivery_date ? formatDate(item.expected_delivery_date) : 'Not specified' }}
            </template>

            <template #[`item.total_amount`]="{ item }">
              <span class="font-weight-bold">KES {{ formatNumber(item.total_amount) }}</span>
            </template>

            <template #[`item.status`]="{ item }">
              <v-chip
                size="small"
                :color="getStatusColor(item.status)"
                variant="tonal"
                class="text-capitalize font-weight-medium text-no-wrap"
                :title="'PO Status: ' + formatStatus(item.status)"
              >
                {{ formatStatus(item.status) }}
              </v-chip>
            </template>

            <template #[`item.actions`]="{ item }">
              <div class="d-flex ga-1">
                <v-btn
                  icon="mdi-eye-outline"
                  size="small"
                  variant="text"
                  color="primary"
                  title="View PO Details"
                  @click="viewPoDetails(item)"
                ></v-btn>

                <v-btn
                  v-if="item.status === 'ordered' || item.status === 'partially_received'"
                  icon="mdi-package-down"
                  size="small"
                  variant="text"
                  color="success"
                  title="Receive Items via GRN"
                  @click="openReceiveGrnDialog(item)"
                ></v-btn>
              </div>
            </template>

            <template #no-data>
              <div class="text-center py-6 text-grey">
                <v-icon icon="mdi-file-cancel-outline" size="40" class="mb-2"></v-icon>
                <p>No purchase orders found matching the filter.</p>
              </div>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>

      <!-- ========================================== -->
      <!-- TAB 2: GOODS RECEIVED NOTES (GRN)          -->
      <!-- ========================================== -->
      <v-window-item value="grn">
        <v-card class="elevation-1 rounded-lg pa-4">
          <v-row density="compact" class="mb-2" align="center">
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="grnSearch"
                label="Search GRN #, Delivery Note, or Supplier..."
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @update:model-value="onGrnSearchDebounced"
                @click:clear="loadGrns"
              ></v-text-field>
            </v-col>

            <v-col cols="12" sm="6" class="d-flex justify-end ga-2">
              <v-btn
                prepend-icon="mdi-plus"
                color="success"
                density="compact"
                @click="openReceiveGrnDialog()"
                class="text-none"
              >
                Receive New Stock
              </v-btn>
              <v-btn
                prepend-icon="mdi-refresh"
                variant="tonal"
                density="compact"
                @click="loadGrns"
                class="text-none"
              >
                Refresh
              </v-btn>
            </v-col>
          </v-row>

          <v-data-table
            :headers="grnHeaders"
            :items="grns"
            :loading="loadingGrns"
            density="comfortable"
            hover
            class="elevation-0"
          >
            <template #[`item.grn_number`]="{ item }">
              <span class="font-weight-bold text-success">{{ item.grn_number }}</span>
            </template>

            <template #[`item.supplier`]="{ item }">
              <div class="font-weight-medium">{{ item.supplier?.name || 'N/A' }}</div>
            </template>

            <template #[`item.delivery_note_number`]="{ item }">
              <span>{{ item.delivery_note_number || '—' }}</span>
            </template>

            <template #[`item.supplier_invoice_number`]="{ item }">
              <span class="text-caption font-weight-medium">{{ item.supplier_invoice_number || '—' }}</span>
            </template>

            <template #[`item.received_date`]="{ item }">
              {{ formatDate(item.received_date) }}
            </template>

            <template #[`item.total_received_value`]="{ item }">
              <span class="font-weight-bold">KES {{ formatNumber(item.total_received_value) }}</span>
            </template>

            <template #[`item.receiver`]="{ item }">
              <span class="text-caption text-grey-darken-2">{{ item.receiver?.name || 'Staff' }}</span>
            </template>

            <template #[`item.actions`]="{ item }">
              <v-btn
                icon="mdi-eye-outline"
                size="small"
                variant="text"
                color="primary"
                title="View GRN Details"
                @click="viewGrnDetails(item)"
              ></v-btn>
            </template>

            <template #no-data>
              <div class="text-center py-6 text-grey">
                <v-icon icon="mdi-package-variant-closed" size="40" class="mb-2"></v-icon>
                <p>No Goods Received Notes recorded yet.</p>
              </div>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>

      <!-- ========================================== -->
      <!-- TAB 3: SUPPLIERS & INVOICES (PAYABLES)     -->
      <!-- ========================================== -->
      <v-window-item value="invoices">
        <v-row>
          <!-- Left: Suppliers Overview -->
          <v-col cols="12" md="5">
            <v-card class="elevation-1 rounded-lg pa-4 h-100 d-flex flex-column">
              <div class="d-flex justify-space-between align-center mb-3">
                <h3 class="text-subtitle-1 font-weight-bold">Suppliers Directory</h3>
                <v-btn
                  size="small"
                  color="primary"
                  variant="tonal"
                  prepend-icon="mdi-plus"
                  @click="openAddSupplierDialog"
                  class="text-none"
                >
                  Add Supplier
                </v-btn>
              </div>

              <v-list density="compact" lines="two">
                <template v-for="(sup, idx) in suppliers" :key="sup.id">
                  <v-list-item class="px-2 py-2 border-b">
                    <template #prepend>
                      <v-avatar color="indigo-lighten-4" size="36">
                        <v-icon icon="mdi-domain" color="indigo-darken-3"></v-icon>
                      </v-avatar>
                    </template>

                    <v-list-item-title class="font-weight-bold text-body-2">
                      {{ sup.name }}
                    </v-list-item-title>
                    <v-list-item-subtitle class="text-caption text-grey">
                      {{ sup.phone }} • Terms: {{ sup.payment_terms_days }} days
                    </v-list-item-subtitle>

                    <template #append>
                      <div class="text-right">
                        <div
                          class="font-weight-bold text-body-2"
                          :class="sup.outstanding_balance > 0 ? 'text-error' : 'text-success'"
                        >
                          KES {{ formatNumber(sup.outstanding_balance) }}
                        </div>
                        <v-btn
                          v-if="sup.outstanding_balance > 0"
                          size="x-small"
                          color="primary"
                          variant="flat"
                          class="text-none mt-1"
                          @click="openPaymentDialog(sup)"
                        >
                          Pay
                        </v-btn>
                      </div>
                    </template>
                  </v-list-item>
                </template>
              </v-list>
            </v-card>
          </v-col>

          <!-- Right: Invoices Table -->
          <v-col cols="12" md="7">
            <v-card class="elevation-1 rounded-lg pa-4 h-100 d-flex flex-column">
              <div class="d-flex justify-space-between align-center mb-3">
                <h3 class="text-subtitle-1 font-weight-bold">Supplier Invoices & Bills</h3>
                <div class="d-flex ga-2">
                  <v-select
                    v-model="invoiceStatusFilter"
                    :items="['all', 'unpaid', 'partial', 'paid']"
                    label="Status"
                    variant="outlined"
                    density="compact"
                    hide-details
                    style="width: 140px;"
                    @update:model-value="loadInvoices"
                  ></v-select>
                </div>
              </div>

              <v-data-table
                :headers="invoiceHeaders"
                :items="invoices"
                :loading="loadingInvoices"
                density="compact"
                hover
                class="elevation-0"
              >
                <template #[`item.invoice_number`]="{ item }">
                  <span class="font-weight-medium">{{ item.invoice_number }}</span>
                </template>

                <template #[`item.supplier`]="{ item }">
                  {{ item.supplier?.name || 'N/A' }}
                </template>

                <template #[`item.due_date`]="{ item }">
                  <span :class="isOverdue(item) ? 'text-error font-weight-bold' : ''">
                    {{ formatDate(item.due_date) }}
                  </span>
                  <v-chip
                    v-if="isOverdue(item)"
                    size="x-small"
                    color="error"
                    class="ml-1 text-no-wrap font-weight-bold"
                    title="Invoice payment is past agreed supplier credit terms"
                  >
                    Overdue
                  </v-chip>
                </template>

                <template #[`item.total_amount`]="{ item }">
                  KES {{ formatNumber(item.total_amount) }}
                </template>

                <template #[`item.balance_due`]="{ item }">
                  <span
                    class="font-weight-bold"
                    :class="item.balance_due > 0 ? 'text-error' : 'text-success'"
                    :title="'Remaining payable balance: KES ' + formatNumber(item.balance_due)"
                  >
                    KES {{ formatNumber(item.balance_due) }}
                  </span>
                </template>

                <template #[`item.payment_status`]="{ item }">
                  <v-chip
                    size="x-small"
                    :color="item.payment_status === 'paid' ? 'success' : (item.payment_status === 'partial' ? 'warning' : 'error')"
                    variant="tonal"
                    class="text-capitalize text-no-wrap font-weight-bold"
                    :title="'Payment status: ' + item.payment_status.toUpperCase()"
                  >
                    {{ item.payment_status }}
                  </v-chip>
                </template>

                <template #[`item.actions`]="{ item }">
                  <v-btn
                    v-if="item.balance_due > 0"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    class="text-none"
                    @click="openPaymentDialogForInvoice(item)"
                  >
                    Pay Bill
                  </v-btn>
                </template>
              </v-data-table>
            </v-card>
          </v-col>
        </v-row>
      </v-window-item>

      <!-- ========================================== -->
      <!-- TAB 4: PURCHASE RETURNS                    -->
      <!-- ========================================== -->
      <v-window-item value="returns">
        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="d-flex justify-space-between align-center mb-3">
            <h3 class="text-subtitle-1 font-weight-bold">Supplier Returns Audit Trail</h3>
            <v-btn
              color="error"
              prepend-icon="mdi-plus"
              density="compact"
              @click="openReturnDialog"
              class="text-none"
            >
              New Return
            </v-btn>
          </div>

          <v-data-table
            :headers="returnHeaders"
            :items="purchaseReturns"
            :loading="loadingReturns"
            density="comfortable"
            hover
            class="elevation-0"
          >
            <template #[`item.return_number`]="{ item }">
              <span class="font-weight-bold text-error">{{ item.return_number }}</span>
            </template>

            <template #[`item.supplier`]="{ item }">
              {{ item.supplier?.name || 'N/A' }}
            </template>

            <template #[`item.return_date`]="{ item }">
              {{ formatDate(item.return_date) }}
            </template>

            <template #[`item.total_amount`]="{ item }">
              <span class="font-weight-bold text-error">KES {{ formatNumber(item.total_amount) }}</span>
            </template>

            <template #[`item.reason`]="{ item }">
              <span class="text-caption">{{ item.reason }}</span>
            </template>

            <template #[`item.processor`]="{ item }">
              <span class="text-caption text-grey-darken-2">{{ item.processor?.name || 'Staff' }}</span>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>
    </v-window>

    <!-- ========================================== -->
    <!-- MODAL: CREATE PURCHASE ORDER               -->
    <!-- ========================================== -->
    <v-dialog v-model="createPoDialog" max-width="900px" persistent>
      <v-card class="rounded-lg">
        <v-card-title class="bg-primary text-white d-flex justify-space-between align-center pa-4">
          <span class="text-h6 font-weight-bold">Create New Purchase Order</span>
          <v-btn icon="mdi-close" variant="text" color="white" density="compact" @click="createPoDialog = false"></v-btn>
        </v-card-title>

        <v-card-text class="pa-4">
          <v-form ref="poForm">
            <v-row density="compact">
              <v-col cols="12" sm="6">
                <v-select
                  v-model="newPo.supplier_id"
                  :items="suppliers"
                  item-title="name"
                  item-value="id"
                  label="Select Supplier *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Supplier is required']"
                ></v-select>
              </v-col>

              <v-col cols="12" sm="3">
                <v-text-field
                  v-model="newPo.order_date"
                  type="date"
                  label="Order Date *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Order date is required']"
                ></v-text-field>
              </v-col>

              <v-col cols="12" sm="3">
                <v-text-field
                  v-model="newPo.expected_delivery_date"
                  type="date"
                  label="Expected Delivery"
                  variant="outlined"
                  density="compact"
                ></v-text-field>
              </v-col>
            </v-row>

            <!-- Line Items Table -->
            <div class="d-flex justify-space-between align-center mt-3 mb-2">
              <h4 class="text-subtitle-2 font-weight-bold">Order Items</h4>
              <v-btn size="small" color="primary" variant="tonal" prepend-icon="mdi-plus" @click="addPoItemRow">
                Add Product
              </v-btn>
            </div>

            <v-table density="compact" class="border rounded mb-3">
              <thead>
                <tr class="bg-grey-lighten-4">
                  <th style="width: 40%;">Product</th>
                  <th style="width: 15%;">Qty Ordered</th>
                  <th style="width: 20%;">Unit Cost (KES)</th>
                  <th style="width: 20%;">Line Subtotal</th>
                  <th style="width: 5%;"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in newPo.items" :key="idx">
                  <td>
                    <v-autocomplete
                      v-model="row.product_id"
                      :items="allProducts"
                      item-title="name"
                      item-value="id"
                      placeholder="Select medicine"
                      variant="plain"
                      density="compact"
                      hide-details
                      @update:model-value="onPoProductSelected(row)"
                    ></v-autocomplete>
                  </td>
                  <td>
                    <v-text-field
                      v-model.number="row.quantity_ordered"
                      type="number"
                      min="1"
                      variant="plain"
                      density="compact"
                      hide-details
                    ></v-text-field>
                  </td>
                  <td>
                    <v-text-field
                      v-model.number="row.unit_cost"
                      type="number"
                      min="0"
                      step="0.01"
                      variant="plain"
                      density="compact"
                      hide-details
                    ></v-text-field>
                  </td>
                  <td class="font-weight-medium">
                    KES {{ formatNumber((row.quantity_ordered || 0) * (row.unit_cost || 0)) }}
                  </td>
                  <td>
                    <v-btn
                      icon="mdi-delete-outline"
                      size="x-small"
                      color="error"
                      variant="text"
                      @click="removePoItemRow(idx)"
                      :disabled="newPo.items.length <= 1"
                    ></v-btn>
                  </td>
                </tr>
              </tbody>
            </v-table>

            <v-row density="compact" class="mt-2">
              <v-col cols="12" sm="8">
                <v-textarea
                  v-model="newPo.notes"
                  label="Instructions / Notes"
                  rows="2"
                  variant="outlined"
                  density="compact"
                  placeholder="e.g. Please deliver by 10 AM, cold chain items to be packed with ice."
                ></v-textarea>
              </v-col>

              <v-col cols="12" sm="4">
                <div class="pa-3 bg-grey-lighten-4 rounded text-right">
                  <div class="d-flex justify-space-between mb-1 text-caption">
                    <span>Subtotal:</span>
                    <span>KES {{ formatNumber(calculatePoSubtotal()) }}</span>
                  </div>
                  <div class="d-flex justify-space-between align-center mb-1">
                    <span class="text-caption">Shipping:</span>
                    <v-text-field
                      v-model.number="newPo.shipping_cost"
                      type="number"
                      min="0"
                      density="compact"
                      variant="underlined"
                      hide-details
                      style="max-width: 90px;"
                      class="text-right pa-0"
                    ></v-text-field>
                  </div>
                  <v-divider class="my-2"></v-divider>
                  <div class="d-flex justify-space-between font-weight-bold text-subtitle-2 text-primary">
                    <span>Grand Total:</span>
                    <span>KES {{ formatNumber(calculatePoGrandTotal()) }}</span>
                  </div>
                </div>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-card-actions class="pa-4 bg-grey-lighten-5">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="createPoDialog = false">Cancel</v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="submittingPo"
            @click="submitPurchaseOrder"
            class="px-5 font-weight-medium"
          >
            Create Purchase Order
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ========================================== -->
    <!-- MODAL: RECEIVE STOCK (GRN)                 -->
    <!-- ========================================== -->
    <v-dialog v-model="receiveGrnDialog" max-width="1050px" persistent>
      <v-card class="rounded-lg">
        <v-card-title class="bg-success text-white d-flex justify-space-between align-center pa-4">
          <span class="text-h6 font-weight-bold">Receive Stock into Inventory (Goods Received Note)</span>
          <v-btn icon="mdi-close" variant="text" color="white" density="compact" @click="receiveGrnDialog = false"></v-btn>
        </v-card-title>

        <v-card-text class="pa-4">
          <v-form ref="grnForm">
            <v-row density="compact">
              <v-col cols="12" sm="4">
                <v-select
                  v-model="newGrn.supplier_id"
                  :items="suppliers"
                  item-title="name"
                  item-value="id"
                  label="Supplier *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Supplier is required']"
                  @update:model-value="onGrnSupplierChanged"
                ></v-select>
              </v-col>

              <v-col cols="12" sm="4">
                <v-select
                  v-model="newGrn.purchase_order_id"
                  :items="filteredPendingPos"
                  item-title="po_number"
                  item-value="id"
                  label="Link Purchase Order (Optional)"
                  variant="outlined"
                  density="compact"
                  clearable
                  @update:model-value="onLinkPoSelected"
                ></v-select>
              </v-col>

              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="newGrn.received_date"
                  type="date"
                  label="Received Date *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Received date is required']"
                ></v-text-field>
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="newGrn.delivery_note_number"
                  label="Delivery Note # / Challan"
                  placeholder="DN-12345"
                  variant="outlined"
                  density="compact"
                ></v-text-field>
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="newGrn.supplier_invoice_number"
                  label="Supplier Invoice # (Creates AP Bill)"
                  placeholder="INV-998811"
                  variant="outlined"
                  density="compact"
                ></v-text-field>
              </v-col>
            </v-row>

            <!-- Line Items Table -->
            <div class="d-flex justify-space-between align-center mt-3 mb-2">
              <div>
                <h4 class="text-subtitle-2 font-weight-bold">Batch & Stock Intake Details</h4>
                <p class="text-caption text-grey">All received medicines create FEFO batches and stock movement records.</p>
              </div>
              <v-btn size="small" color="success" variant="tonal" prepend-icon="mdi-plus" @click="addGrnItemRow">
                Add Item
              </v-btn>
            </div>

            <v-table density="compact" class="border rounded mb-3">
              <thead>
                <tr class="bg-grey-lighten-4">
                  <th style="width: 25%;">Medicine</th>
                  <th style="width: 15%;">Batch # *</th>
                  <th style="width: 15%;">Expiry Date *</th>
                  <th style="width: 12%;">Qty *</th>
                  <th style="width: 15%;">Buy Price (KES)</th>
                  <th style="width: 15%;">Subtotal</th>
                  <th style="width: 3%;"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in newGrn.items" :key="idx">
                  <td>
                    <v-autocomplete
                      v-model="row.product_id"
                      :items="allProducts"
                      item-title="name"
                      item-value="id"
                      placeholder="Select medicine"
                      variant="plain"
                      density="compact"
                      hide-details
                      @update:model-value="onGrnProductSelected(row)"
                    ></v-autocomplete>
                  </td>
                  <td>
                    <v-text-field
                      v-model="row.batch_number"
                      placeholder="e.g. B2026-X"
                      variant="plain"
                      density="compact"
                      hide-details
                    ></v-text-field>
                  </td>
                  <td>
                    <v-text-field
                      v-model="row.expiry_date"
                      type="date"
                      variant="plain"
                      density="compact"
                      hide-details
                    ></v-text-field>
                  </td>
                  <td>
                    <v-text-field
                      v-model.number="row.quantity_received"
                      type="number"
                      min="1"
                      variant="plain"
                      density="compact"
                      hide-details
                    ></v-text-field>
                  </td>
                  <td>
                    <v-text-field
                      v-model.number="row.unit_purchase_price"
                      type="number"
                      min="0"
                      step="0.01"
                      variant="plain"
                      density="compact"
                      hide-details
                    ></v-text-field>
                  </td>
                  <td class="font-weight-medium">
                    KES {{ formatNumber((row.quantity_received || 0) * (row.unit_purchase_price || 0)) }}
                  </td>
                  <td>
                    <v-btn
                      icon="mdi-delete-outline"
                      size="x-small"
                      color="error"
                      variant="text"
                      @click="removeGrnItemRow(idx)"
                      :disabled="newGrn.items.length <= 1"
                    ></v-btn>
                  </td>
                </tr>
              </tbody>
            </v-table>

            <div class="d-flex justify-space-between align-center pa-3 bg-green-lighten-5 rounded border-green">
              <div>
                <span class="text-caption font-weight-medium text-green-darken-3">Inventory Value Added:</span>
              </div>
              <div class="text-h6 font-weight-bold text-green-darken-4">
                KES {{ formatNumber(calculateGrnTotal()) }}
              </div>
            </div>
          </v-form>
        </v-card-text>

        <v-card-actions class="pa-4 bg-grey-lighten-5">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="receiveGrnDialog = false">Cancel</v-btn>
          <v-btn
            color="success"
            variant="flat"
            :loading="submittingGrn"
            @click="submitReceiveGrn"
            class="px-5 font-weight-medium"
          >
            Confirm & Ingest Stock
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ========================================== -->
    <!-- MODAL: RECORD SUPPLIER PAYMENT             -->
    <!-- ========================================== -->
    <v-dialog v-model="paymentDialog" max-width="600px" persistent>
      <v-card class="rounded-lg">
        <v-card-title class="bg-indigo-darken-1 text-white d-flex justify-space-between align-center pa-4">
          <span class="text-h6 font-weight-bold">Record Supplier Payment</span>
          <v-btn icon="mdi-close" variant="text" color="white" density="compact" @click="paymentDialog = false"></v-btn>
        </v-card-title>

        <v-card-text class="pa-4">
          <v-form ref="paymentForm">
            <v-select
              v-model="newPayment.supplier_id"
              :items="suppliers"
              item-title="name"
              item-value="id"
              label="Supplier *"
              variant="outlined"
              density="compact"
              class="mb-3"
              @update:model-value="onPaymentSupplierChanged"
            ></v-select>

            <v-select
              v-model="newPayment.supplier_invoice_id"
              :items="supplierPendingInvoices"
              item-title="display_text"
              item-value="id"
              label="Select Invoice / Bill to Settle"
              variant="outlined"
              density="compact"
              clearable
              class="mb-3"
              @update:model-value="onPaymentInvoiceSelected"
            ></v-select>

            <v-text-field
              v-model.number="newPayment.amount"
              type="number"
              min="1"
              step="0.01"
              label="Amount to Pay (KES) *"
              prefix="KES"
              variant="outlined"
              density="compact"
              class="mb-3"
              :rules="[v => !!v && v > 0 || 'Valid payment amount required']"
            ></v-text-field>

            <v-row density="compact" class="mb-3">
              <v-col cols="12" sm="6">
                <v-select
                  v-model="newPayment.payment_method"
                  :items="['bank_transfer', 'mpesa', 'cash', 'cheque']"
                  label="Payment Method *"
                  variant="outlined"
                  density="compact"
                ></v-select>
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="newPayment.reference_number"
                  label="Reference / Transaction #"
                  placeholder="e.g. EFT-0098, QDX45..."
                  variant="outlined"
                  density="compact"
                ></v-text-field>
              </v-col>
            </v-row>

            <v-text-field
              v-model="newPayment.notes"
              label="Notes"
              variant="outlined"
              density="compact"
              placeholder="e.g. Paid via corporate internet banking"
            ></v-text-field>
          </v-form>
        </v-card-text>

        <v-card-actions class="pa-4 bg-grey-lighten-5">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="paymentDialog = false">Cancel</v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="submittingPayment"
            @click="submitSupplierPayment"
            class="px-5 font-weight-medium"
          >
            Record Payment
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ========================================== -->
    <!-- MODAL: ADD / EDIT SUPPLIER                 -->
    <!-- ========================================== -->
    <v-dialog v-model="supplierDialog" max-width="600px" persistent>
      <v-card class="rounded-lg">
        <v-card-title class="bg-primary text-white d-flex justify-space-between align-center pa-4">
          <span class="text-h6 font-weight-bold">Add New Supplier</span>
          <v-btn icon="mdi-close" variant="text" color="white" density="compact" @click="supplierDialog = false"></v-btn>
        </v-card-title>

        <v-card-text class="pa-4">
          <v-row density="compact">
            <v-col cols="12">
              <v-text-field
                v-model="newSupplier.name"
                label="Supplier Company Name *"
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="newSupplier.contact_person"
                label="Contact Person"
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="newSupplier.phone"
                label="Phone Number *"
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="newSupplier.email"
                type="email"
                label="Email Address"
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="newSupplier.payment_terms_days"
                type="number"
                label="Payment Terms (Days)"
                placeholder="30"
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
          </v-row>
        </v-card-text>

        <v-card-actions class="pa-4 bg-grey-lighten-5">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="supplierDialog = false">Cancel</v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="submittingSupplier"
            @click="submitSupplier"
            class="px-5 font-weight-medium"
          >
            Save Supplier
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ========================================== -->
    <!-- MODAL: PROCESS SUPPLIER RETURN             -->
    <!-- ========================================== -->
    <v-dialog v-model="returnDialog" max-width="800px" persistent>
      <v-card class="rounded-lg">
        <v-card-title class="bg-error text-white d-flex justify-space-between align-center pa-4">
          <span class="text-h6 font-weight-bold">Return Goods to Supplier</span>
          <v-btn icon="mdi-close" variant="text" color="white" density="compact" @click="returnDialog = false"></v-btn>
        </v-card-title>

        <v-card-text class="pa-4">
          <v-row density="compact" class="mb-3">
            <v-col cols="12" sm="6">
              <v-select
                v-model="newReturn.supplier_id"
                :items="suppliers"
                item-title="name"
                item-value="id"
                label="Supplier *"
                variant="outlined"
                density="compact"
                @update:model-value="loadSupplierBatches"
              ></v-select>
            </v-col>

            <v-col cols="12" sm="6">
              <v-text-field
                v-model="newReturn.reason"
                label="Return Reason *"
                placeholder="e.g. Expired stock recall, damaged packaging"
                variant="outlined"
                density="compact"
              ></v-text-field>
            </v-col>
          </v-row>

          <!-- Return Items -->
          <v-table density="compact" class="border rounded mb-3">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th style="width: 50%;">Select Batch / Medicine</th>
                <th style="width: 20%;">Qty to Return</th>
                <th style="width: 25%;">Unit Cost</th>
                <th style="width: 5%;"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in newReturn.items" :key="idx">
                <td>
                  <v-autocomplete
                    v-model="row.product_batch_id"
                    :items="supplierBatches"
                    item-title="display_label"
                    item-value="id"
                    placeholder="Select batch"
                    variant="plain"
                    density="compact"
                    hide-details
                    @update:model-value="onReturnBatchSelected(row)"
                  ></v-autocomplete>
                </td>
                <td>
                  <v-text-field
                    v-model.number="row.quantity"
                    type="number"
                    min="1"
                    variant="plain"
                    density="compact"
                    hide-details
                  ></v-text-field>
                </td>
                <td>
                  <span class="font-weight-medium">KES {{ formatNumber(row.unit_cost || 0) }}</span>
                </td>
                <td>
                  <v-btn
                    icon="mdi-delete-outline"
                    size="x-small"
                    color="error"
                    variant="text"
                    @click="removeReturnItemRow(idx)"
                    :disabled="newReturn.items.length <= 1"
                  ></v-btn>
                </td>
              </tr>
            </tbody>
          </v-table>
          <v-btn size="small" color="error" variant="tonal" prepend-icon="mdi-plus" @click="addReturnItemRow">
            Add Another Batch
          </v-btn>
        </v-card-text>

        <v-card-actions class="pa-4 bg-grey-lighten-5">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="returnDialog = false">Cancel</v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="submittingReturn"
            @click="submitPurchaseReturn"
            class="px-5 font-weight-medium"
          >
            Confirm & Return Stock
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ========================================== -->
    <!-- MODAL: VIEW PO DETAILS                     -->
    <!-- ========================================== -->
    <v-dialog v-model="viewPoDialog" max-width="850px">
      <v-card v-if="selectedPo" class="rounded-lg">
        <v-card-title class="bg-grey-lighten-4 d-flex justify-space-between align-center pa-4">
          <div>
            <span class="text-h6 font-weight-bold">{{ selectedPo.po_number }}</span>
            <v-chip size="small" :color="getStatusColor(selectedPo.status)" class="ml-2 text-capitalize">
              {{ formatStatus(selectedPo.status) }}
            </v-chip>
          </div>
          <v-btn icon="mdi-close" variant="text" density="compact" @click="viewPoDialog = false"></v-btn>
        </v-card-title>

        <v-card-text class="pa-4">
          <v-row density="compact" class="mb-3">
            <v-col cols="6" sm="3">
              <span class="text-caption text-grey">Supplier:</span>
              <p class="font-weight-bold">{{ selectedPo.supplier?.name }}</p>
            </v-col>
            <v-col cols="6" sm="3">
              <span class="text-caption text-grey">Order Date:</span>
              <p class="font-weight-medium">{{ formatDate(selectedPo.order_date) }}</p>
            </v-col>
            <v-col cols="6" sm="3">
              <span class="text-caption text-grey">Expected Date:</span>
              <p class="font-weight-medium">{{ selectedPo.expected_delivery_date ? formatDate(selectedPo.expected_delivery_date) : 'N/A' }}</p>
            </v-col>
            <v-col cols="6" sm="3">
              <span class="text-caption text-grey">Total Value:</span>
              <p class="font-weight-bold text-primary">KES {{ formatNumber(selectedPo.total_amount) }}</p>
            </v-col>
          </v-row>

          <h4 class="text-subtitle-2 font-weight-bold mb-2">Ordered Line Items</h4>
          <v-table density="compact" class="border rounded mb-3">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th>Product</th>
                <th>Ordered</th>
                <th>Received</th>
                <th>Fulfillment</th>
                <th>Unit Cost</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in selectedPo.items" :key="item.id">
                <td class="font-weight-medium">{{ item.product?.name }}</td>
                <td>{{ item.quantity_ordered }}</td>
                <td>{{ item.quantity_received }}</td>
                <td style="width: 150px;">
                  <v-progress-linear
                    :model-value="getFulfillmentPercent(item)"
                    :color="item.quantity_received >= item.quantity_ordered ? 'success' : 'primary'"
                    height="12"
                    rounded
                  >
                    <template #default="{ value }">
                      <strong class="text-caption" style="font-size: 10px;">{{ Math.ceil(value) }}%</strong>
                    </template>
                  </v-progress-linear>
                </td>
                <td>KES {{ formatNumber(item.unit_cost) }}</td>
                <td class="font-weight-medium">KES {{ formatNumber(item.subtotal) }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>

        <v-card-actions class="pa-4 bg-grey-lighten-5">
          <v-btn
            v-if="selectedPo.status === 'ordered' || selectedPo.status === 'partially_received'"
            color="success"
            variant="flat"
            prepend-icon="mdi-package-down"
            @click="receiveFromPo(selectedPo)"
          >
            Receive Goods (GRN)
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="viewPoDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ========================================== -->
    <!-- MODAL: VIEW GRN DETAILS                    -->
    <!-- ========================================== -->
    <v-dialog v-model="viewGrnDialog" max-width="850px">
      <v-card v-if="selectedGrn" class="rounded-lg">
        <v-card-title class="bg-grey-lighten-4 d-flex justify-space-between align-center pa-4">
          <div>
            <span class="text-h6 font-weight-bold">{{ selectedGrn.grn_number }}</span>
            <v-chip size="small" color="success" class="ml-2">Received & Ingested</v-chip>
          </div>
          <v-btn icon="mdi-close" variant="text" density="compact" @click="viewGrnDialog = false"></v-btn>
        </v-card-title>

        <v-card-text class="pa-4">
          <v-row density="compact" class="mb-3">
            <v-col cols="6" sm="3">
              <span class="text-caption text-grey">Supplier:</span>
              <p class="font-weight-bold">{{ selectedGrn.supplier?.name }}</p>
            </v-col>
            <v-col cols="6" sm="3">
              <span class="text-caption text-grey">Received Date:</span>
              <p class="font-weight-medium">{{ formatDate(selectedGrn.received_date) }}</p>
            </v-col>
            <v-col cols="6" sm="3">
              <span class="text-caption text-grey">Delivery Note:</span>
              <p class="font-weight-medium">{{ selectedGrn.delivery_note_number || 'N/A' }}</p>
            </v-col>
            <v-col cols="6" sm="3">
              <span class="text-caption text-grey">Stock Value:</span>
              <p class="font-weight-bold text-success">KES {{ formatNumber(selectedGrn.total_received_value) }}</p>
            </v-col>
          </v-row>

          <h4 class="text-subtitle-2 font-weight-bold mb-2">Ingested Batches & Quantities</h4>
          <v-table density="compact" class="border rounded mb-3">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th>Product</th>
                <th>Batch #</th>
                <th>Expiry Date</th>
                <th>Qty Received</th>
                <th>Unit Purchase Price</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in selectedGrn.items" :key="item.id">
                <td class="font-weight-medium">{{ item.product?.name }}</td>
                <td><v-chip size="x-small" color="primary">{{ item.batch_number }}</v-chip></td>
                <td>{{ formatDate(item.expiry_date) }}</td>
                <td>{{ item.quantity_received }}</td>
                <td>KES {{ formatNumber(item.unit_purchase_price) }}</td>
                <td class="font-weight-medium">KES {{ formatNumber(item.subtotal_cost) }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>

        <v-card-actions class="pa-4 bg-grey-lighten-5">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="viewGrnDialog = false">Close</v-btn>
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

const notification = useNotificationStore();

// STATE
const activeTab = ref('orders');

const orders = ref([]);
const grns = ref([]);
const suppliers = ref([]);
const invoices = ref([]);
const purchaseReturns = ref([]);
const allProducts = ref([]);
const supplierBatches = ref([]);

const loadingOrders = ref(false);
const loadingGrns = ref(false);
const loadingInvoices = ref(false);
const loadingReturns = ref(false);

const orderSearch = ref('');
const orderStatusFilter = ref('all');
const grnSearch = ref('');
const invoiceStatusFilter = ref('all');

let orderSearchTimeout = null;
let grnSearchTimeout = null;

// Modals
const createPoDialog = ref(false);
const receiveGrnDialog = ref(false);
const paymentDialog = ref(false);
const supplierDialog = ref(false);
const returnDialog = ref(false);
const viewPoDialog = ref(false);
const viewGrnDialog = ref(false);

const submittingPo = ref(false);
const submittingGrn = ref(false);
const submittingPayment = ref(false);
const submittingSupplier = ref(false);
const submittingReturn = ref(false);

const selectedPo = ref(null);
const selectedGrn = ref(null);

// Forms
const newPo = ref({
  supplier_id: null,
  order_date: new Date().toISOString().substring(0, 10),
  expected_delivery_date: '',
  shipping_cost: 0,
  notes: '',
  items: [
    { product_id: null, quantity_ordered: 1, unit_cost: 0, discount_amount: 0, tax_amount: 0 },
  ],
});

const newGrn = ref({
  supplier_id: null,
  purchase_order_id: null,
  delivery_note_number: '',
  supplier_invoice_number: '',
  received_date: new Date().toISOString().substring(0, 10),
  items: [
    { product_id: null, purchase_order_item_id: null, batch_number: '', expiry_date: '', quantity_received: 1, unit_purchase_price: 0, unit_selling_price: 0 },
  ],
});

const newPayment = ref({
  supplier_id: null,
  supplier_invoice_id: null,
  amount: 0,
  payment_method: 'bank_transfer',
  reference_number: '',
  notes: '',
});

const newSupplier = ref({
  name: '',
  contact_person: '',
  phone: '',
  email: '',
  payment_terms_days: 30,
});

const newReturn = ref({
  supplier_id: null,
  reason: '',
  items: [
    { product_batch_id: null, quantity: 1, unit_cost: 0 },
  ],
});

// HEADERS
const orderHeaders = [
  { title: 'PO #', key: 'po_number', sortable: true },
  { title: 'Supplier', key: 'supplier', sortable: false },
  { title: 'Order Date', key: 'order_date', sortable: true },
  { title: 'Expected Date', key: 'expected_delivery_date', sortable: true },
  { title: 'Total Amount', key: 'total_amount', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
];

const grnHeaders = [
  { title: 'GRN #', key: 'grn_number', sortable: true },
  { title: 'Supplier', key: 'supplier', sortable: false },
  { title: 'Delivery Note #', key: 'delivery_note_number' },
  { title: 'Supplier Inv #', key: 'supplier_invoice_number' },
  { title: 'Received Date', key: 'received_date', sortable: true },
  { title: 'Total Stock Value', key: 'total_received_value', sortable: true },
  { title: 'Receiver', key: 'receiver' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
];

const invoiceHeaders = [
  { title: 'Invoice #', key: 'invoice_number' },
  { title: 'Supplier', key: 'supplier' },
  { title: 'Due Date', key: 'due_date', sortable: true },
  { title: 'Total Amount', key: 'total_amount' },
  { title: 'Balance Due', key: 'balance_due' },
  { title: 'Status', key: 'payment_status' },
  { title: 'Action', key: 'actions', sortable: false, align: 'end' },
];

const returnHeaders = [
  { title: 'Return #', key: 'return_number' },
  { title: 'Supplier', key: 'supplier' },
  { title: 'Date', key: 'return_date' },
  { title: 'Total Amount', key: 'total_amount' },
  { title: 'Reason', key: 'reason' },
  { title: 'Staff', key: 'processor' },
];

const orderStatusOptions = [
  { title: 'All Statuses', value: 'all' },
  { title: 'Ordered', value: 'ordered' },
  { title: 'Partially Received', value: 'partially_received' },
  { title: 'Received', value: 'received' },
  { title: 'Cancelled', value: 'cancelled' },
];

// COMPUTED METRICS
const openOrdersCount = computed(() => {
  return orders.value.filter(o => o.status === 'ordered' || o.status === 'partially_received').length;
});

const openOrdersTotal = computed(() => {
  return orders.value
    .filter(o => o.status === 'ordered' || o.status === 'partially_received')
    .reduce((sum, o) => sum + parseFloat(o.total_amount || 0), 0);
});

const grnsCount = computed(() => grns.value.length);
const grnsTotalValue = computed(() => {
  return grns.value.reduce((sum, g) => sum + parseFloat(g.total_received_value || 0), 0);
});

const totalPayable = computed(() => {
  return suppliers.value.reduce((sum, s) => sum + parseFloat(s.outstanding_balance || 0), 0);
});

const overdueInvoices = computed(() => {
  const today = new Date().toISOString().substring(0, 10);
  return invoices.value.filter(i => parseFloat(i.balance_due || 0) > 0 && i.due_date < today);
});

const overdueCount = computed(() => overdueInvoices.value.length);
const overdueTotal = computed(() => {
  return overdueInvoices.value.reduce((sum, i) => sum + parseFloat(i.balance_due || 0), 0);
});

const filteredPendingPos = computed(() => {
  if (!newGrn.value.supplier_id) return [];
  return orders.value.filter(o =>
    o.supplier_id === newGrn.value.supplier_id &&
    (o.status === 'ordered' || o.status === 'partially_received')
  );
});

const supplierPendingInvoices = computed(() => {
  if (!newPayment.value.supplier_id) return [];
  return invoices.value
    .filter(inv => inv.supplier_id === newPayment.value.supplier_id && parseFloat(inv.balance_due) > 0)
    .map(inv => ({
      id: inv.id,
      display_text: `${inv.invoice_number} (Due: KES ${formatNumber(inv.balance_due)})`,
      balance_due: inv.balance_due,
    }));
});

// METHODS
const formatNumber = val => Number(val || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatDate = d => formatIsoDateTime(d);

const getStatusColor = status => {
  switch (status) {
    case 'ordered': return 'info';
    case 'partially_received': return 'warning';
    case 'received': return 'success';
    case 'cancelled': return 'error';
    case 'draft': return 'grey';
    default: return 'primary';
  }
};

const formatStatus = s => (s || '').replace(/_/g, ' ');

const isOverdue = inv => {
  const today = new Date().toISOString().substring(0, 10);
  return parseFloat(inv.balance_due || 0) > 0 && inv.due_date < today;
};

const getFulfillmentPercent = item => {
  const ordered = parseFloat(item.quantity_ordered) || 1;
  const received = parseFloat(item.quantity_received) || 0;
  return Math.min(100, (received / ordered) * 100);
};

// API CALLS
const loadOrders = async () => {
  loadingOrders.value = true;
  try {
    const params = {};
    if (orderStatusFilter.value !== 'all') params.status = orderStatusFilter.value;
    if (orderSearch.value) params.search = orderSearch.value;

    const res = await api.get('/purchase-orders', { params });
    orders.value = res.data.data.data || res.data.data || [];
  } catch (err) {
    notification.error('Failed to load purchase orders');
  } finally {
    loadingOrders.value = false;
  }
};

const onOrderSearchDebounced = () => {
  clearTimeout(orderSearchTimeout);
  orderSearchTimeout = setTimeout(() => {
    loadOrders();
  }, 300);
};

const loadGrns = async () => {
  loadingGrns.value = true;
  try {
    const params = {};
    if (grnSearch.value) params.search = grnSearch.value;
    const res = await api.get('/goods-received-notes', { params });
    grns.value = res.data.data.data || res.data.data || [];
  } catch (err) {
    notification.error('Failed to load Goods Received Notes');
  } finally {
    loadingGrns.value = false;
  }
};

const onGrnSearchDebounced = () => {
  clearTimeout(grnSearchTimeout);
  grnSearchTimeout = setTimeout(() => {
    loadGrns();
  }, 300);
};

const loadSuppliers = async () => {
  try {
    const res = await api.get('/suppliers');
    suppliers.value = res.data.data || [];
  } catch (err) {
    notification.error('Failed to load suppliers');
  }
};

const loadInvoices = async () => {
  loadingInvoices.value = true;
  try {
    const params = {};
    if (invoiceStatusFilter.value !== 'all') params.payment_status = invoiceStatusFilter.value;
    const res = await api.get('/supplier-invoices', { params });
    invoices.value = res.data.data.data || res.data.data || [];
  } catch (err) {
    notification.error('Failed to load supplier invoices');
  } finally {
    loadingInvoices.value = false;
  }
};

const loadReturns = async () => {
  loadingReturns.value = true;
  try {
    const res = await api.get('/purchase-returns');
    purchaseReturns.value = res.data.data.data || res.data.data || [];
  } catch (err) {
    notification.error('Failed to load purchase returns');
  } finally {
    loadingReturns.value = false;
  }
};

const loadProducts = async () => {
  try {
    const res = await api.get('/products', { params: { per_page: 200 } });
    allProducts.value = res.data.data.data || res.data.data || [];
  } catch (err) {
    // optional
  }
};

// Purchase Order Dialog Operations
const openCreatePoDialog = () => {
  newPo.value = {
    supplier_id: suppliers.value[0]?.id || null,
    order_date: new Date().toISOString().substring(0, 10),
    expected_delivery_date: '',
    shipping_cost: 0,
    notes: '',
    items: [
      { product_id: null, quantity_ordered: 1, unit_cost: 0, discount_amount: 0, tax_amount: 0 },
    ],
  };
  createPoDialog.value = true;
};

const addPoItemRow = () => {
  newPo.value.items.push({
    product_id: null,
    quantity_ordered: 1,
    unit_cost: 0,
    discount_amount: 0,
    tax_amount: 0,
  });
};

const removePoItemRow = idx => {
  newPo.value.items.splice(idx, 1);
};

const onPoProductSelected = row => {
  const p = allProducts.value.find(prod => prod.id === row.product_id);
  if (p) {
    row.unit_cost = parseFloat(p.purchase_price || 0);
  }
};

const calculatePoSubtotal = () => {
  return newPo.value.items.reduce((sum, i) => sum + ((parseFloat(i.quantity_ordered) || 0) * (parseFloat(i.unit_cost) || 0)), 0);
};

const calculatePoGrandTotal = () => {
  return calculatePoSubtotal() + (parseFloat(newPo.value.shipping_cost) || 0);
};

const submitPurchaseOrder = async () => {
  if (!newPo.value.supplier_id) {
    notification.warning('Please select a supplier.');
    return;
  }
  const validItems = newPo.value.items.filter(i => i.product_id && i.quantity_ordered > 0);
  if (validItems.length === 0) {
    notification.warning('Please add at least one product with valid quantity.');
    return;
  }

  submittingPo.value = true;
  try {
    await api.post('/purchase-orders', {
      ...newPo.value,
      items: validItems,
    });
    notification.success('Purchase Order created successfully!');
    createPoDialog.value = false;
    loadOrders();
  } catch (err) {
    notification.error(err.response?.data?.message || 'Failed to create purchase order');
  } finally {
    submittingPo.value = false;
  }
};

const viewPoDetails = async item => {
  try {
    const res = await api.get(`/purchase-orders/${item.id}`);
    selectedPo.value = res.data.data;
    viewPoDialog.value = true;
  } catch (err) {
    notification.error('Failed to view PO details');
  }
};

// GRN Stock Reception Operations
const openReceiveGrnDialog = po => {
  newGrn.value = {
    supplier_id: po ? po.supplier_id : (suppliers.value[0]?.id || null),
    purchase_order_id: po ? po.id : null,
    delivery_note_number: '',
    supplier_invoice_number: '',
    received_date: new Date().toISOString().substring(0, 10),
    items: [],
  };

  if (po && po.items && po.items.length > 0) {
    po.items.forEach(pi => {
      const remainingQty = Math.max(0, (parseFloat(pi.quantity_ordered) || 0) - (parseFloat(pi.quantity_received) || 0));
      if (remainingQty > 0) {
        newGrn.value.items.push({
          product_id: pi.product_id,
          purchase_order_item_id: pi.id,
          batch_number: '',
          expiry_date: '',
          quantity_received: remainingQty,
          unit_purchase_price: parseFloat(pi.unit_cost || 0),
          unit_selling_price: parseFloat(pi.unit_cost || 0) * 1.33,
        });
      }
    });
  }

  if (newGrn.value.items.length === 0) {
    newGrn.value.items.push({
      product_id: null,
      purchase_order_item_id: null,
      batch_number: '',
      expiry_date: '',
      quantity_received: 1,
      unit_purchase_price: 0,
      unit_selling_price: 0,
    });
  }

  receiveGrnDialog.value = true;
};

const receiveFromPo = po => {
  viewPoDialog.value = false;
  openReceiveGrnDialog(po);
};

const addGrnItemRow = () => {
  newGrn.value.items.push({
    product_id: null,
    purchase_order_item_id: null,
    batch_number: '',
    expiry_date: '',
    quantity_received: 1,
    unit_purchase_price: 0,
    unit_selling_price: 0,
  });
};

const removeGrnItemRow = idx => {
  newGrn.value.items.splice(idx, 1);
};

const onGrnProductSelected = row => {
  const p = allProducts.value.find(prod => prod.id === row.product_id);
  if (p) {
    row.unit_purchase_price = parseFloat(p.purchase_price || 0);
    row.unit_selling_price = parseFloat(p.selling_price || (p.purchase_price * 1.33));
  }
};

const onGrnSupplierChanged = () => {
  newGrn.value.purchase_order_id = null;
};

const onLinkPoSelected = poId => {
  if (!poId) return;
  const po = orders.value.find(o => o.id === poId);
  if (po) {
    openReceiveGrnDialog(po);
  }
};

const calculateGrnTotal = () => {
  return newGrn.value.items.reduce((sum, i) => sum + ((parseFloat(i.quantity_received) || 0) * (parseFloat(i.unit_purchase_price) || 0)), 0);
};

const submitReceiveGrn = async () => {
  if (!newGrn.value.supplier_id) {
    notification.warning('Please select a supplier.');
    return;
  }
  const validItems = newGrn.value.items.filter(i => i.product_id && i.quantity_received > 0 && i.batch_number && i.expiry_date);
  if (validItems.length === 0) {
    notification.warning('Please provide complete medicine, batch #, and expiry date for all items.');
    return;
  }

  submittingGrn.value = true;
  try {
    const res = await api.post('/goods-received-notes', {
      ...newGrn.value,
      items: validItems,
    });
    notification.success(`GRN ${res.data.data.grn_number} created! Stock batches updated.`);
    receiveGrnDialog.value = false;
    loadGrns();
    loadOrders();
    loadSuppliers();
    loadInvoices();
  } catch (err) {
    notification.error(err.response?.data?.message || 'Failed to ingest stock via GRN');
  } finally {
    submittingGrn.value = false;
  }
};

const viewGrnDetails = async item => {
  try {
    const res = await api.get(`/goods-received-notes/${item.id}`);
    selectedGrn.value = res.data.data;
    viewGrnDialog.value = true;
  } catch (err) {
    notification.error('Failed to load GRN details');
  }
};

// Supplier Payment Operations
const openPaymentDialog = supplier => {
  newPayment.value = {
    supplier_id: supplier ? supplier.id : (suppliers.value[0]?.id || null),
    supplier_invoice_id: null,
    amount: supplier ? parseFloat(supplier.outstanding_balance) : 0,
    payment_method: 'bank_transfer',
    reference_number: '',
    notes: '',
  };
  paymentDialog.value = true;
};

const openPaymentDialogForInvoice = inv => {
  newPayment.value = {
    supplier_id: inv.supplier_id,
    supplier_invoice_id: inv.id,
    amount: parseFloat(inv.balance_due),
    payment_method: 'bank_transfer',
    reference_number: '',
    notes: `Settling invoice #${inv.invoice_number}`,
  };
  paymentDialog.value = true;
};

const onPaymentSupplierChanged = () => {
  newPayment.value.supplier_invoice_id = null;
  const s = suppliers.value.find(sup => sup.id === newPayment.value.supplier_id);
  if (s) {
    newPayment.value.amount = parseFloat(s.outstanding_balance || 0);
  }
};

const onPaymentInvoiceSelected = invId => {
  const inv = invoices.value.find(i => i.id === invId);
  if (inv) {
    newPayment.value.amount = parseFloat(inv.balance_due || 0);
  }
};

const submitSupplierPayment = async () => {
  if (!newPayment.value.supplier_id || newPayment.value.amount <= 0) {
    notification.warning('Please enter a valid supplier and amount.');
    return;
  }

  submittingPayment.value = true;
  try {
    await api.post('/supplier-payments', newPayment.value);
    notification.success('Supplier payment recorded successfully!');
    paymentDialog.value = false;
    loadSuppliers();
    loadInvoices();
  } catch (err) {
    notification.error(err.response?.data?.message || 'Failed to record supplier payment');
  } finally {
    submittingPayment.value = false;
  }
};

// Add Supplier Operations
const openAddSupplierDialog = () => {
  newSupplier.value = {
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    payment_terms_days: 30,
  };
  supplierDialog.value = true;
};

const submitSupplier = async () => {
  if (!newSupplier.value.name || !newSupplier.value.phone) {
    notification.warning('Name and phone are required.');
    return;
  }

  submittingSupplier.value = true;
  try {
    await api.post('/suppliers', newSupplier.value);
    notification.success('Supplier added successfully!');
    supplierDialog.value = false;
    loadSuppliers();
  } catch (err) {
    notification.error(err.response?.data?.message || 'Failed to add supplier');
  } finally {
    submittingSupplier.value = false;
  }
};

// Purchase Return Operations
const openReturnDialog = () => {
  newReturn.value = {
    supplier_id: suppliers.value[0]?.id || null,
    reason: '',
    items: [
      { product_batch_id: null, quantity: 1, unit_cost: 0 },
    ],
  };
  loadSupplierBatches();
  returnDialog.value = true;
};

const loadSupplierBatches = async () => {
  try {
    const res = await api.get('/batches', {
      params: { supplier_id: newReturn.value.supplier_id, per_page: 100 },
    });
    const batchList = res.data.data.data || res.data.data || [];
    supplierBatches.value = batchList.map(b => ({
      id: b.id,
      display_label: `${b.product?.name || 'Item'} (Batch: ${b.batch_number}, In Stock: ${b.quantity_remaining}, Exp: ${formatDate(b.expiry_date)})`,
      quantity_remaining: b.quantity_remaining,
      purchase_price_per_unit: b.purchase_price_per_unit,
    }));
  } catch (err) {
    // optional
  }
};

const addReturnItemRow = () => {
  newReturn.value.items.push({ product_batch_id: null, quantity: 1, unit_cost: 0 });
};

const removeReturnItemRow = idx => {
  newReturn.value.items.splice(idx, 1);
};

const onReturnBatchSelected = row => {
  const b = supplierBatches.value.find(item => item.id === row.product_batch_id);
  if (b) {
    row.unit_cost = parseFloat(b.purchase_price_per_unit || 0);
  }
};

const submitPurchaseReturn = async () => {
  if (!newReturn.value.supplier_id || !newReturn.value.reason) {
    notification.warning('Supplier and reason are required.');
    return;
  }
  const validItems = newReturn.value.items.filter(i => i.product_batch_id && i.quantity > 0);
  if (validItems.length === 0) {
    notification.warning('Please select at least one batch to return.');
    return;
  }

  submittingReturn.value = true;
  try {
    await api.post('/purchase-returns', {
      ...newReturn.value,
      items: validItems,
    });
    notification.success('Purchase return processed successfully!');
    returnDialog.value = false;
    loadReturns();
    loadSuppliers();
  } catch (err) {
    notification.error(err.response?.data?.message || 'Failed to process purchase return');
  } finally {
    submittingReturn.value = false;
  }
};

onMounted(() => {
  loadOrders();
  loadGrns();
  loadSuppliers();
  loadInvoices();
  loadReturns();
  loadProducts();
});
</script>
