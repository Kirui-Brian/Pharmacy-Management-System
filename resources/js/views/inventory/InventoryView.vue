<template>
  <div>
    <!-- Standard Header Card -->
    <v-card class="elevation-1 rounded-lg pa-4 mb-4" color="white">
      <div class="d-flex flex-wrap justify-space-between align-center ga-3">
        <div class="d-flex align-center">
          <v-avatar color="teal-darken-2" class="mr-3" size="44">
            <v-icon icon="mdi-pill" color="white" size="24"></v-icon>
          </v-avatar>
          <div>
            <h1 class="text-h6 font-weight-bold">Inventory & Batch Management</h1>
            <p class="text-caption text-grey">FEFO batch tracking, pack-to-unit conversions, and expiry control</p>
          </div>
        </div>

        <div class="d-flex flex-wrap ga-2">
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            class="font-weight-medium text-none"
            @click="openAddProductModal"
          >
            Add New Medicine
          </v-btn>
          <v-btn
            color="teal-darken-2"
            variant="outlined"
            prepend-icon="mdi-tray-arrow-down"
            class="font-weight-medium text-none"
            @click="openReceiveBatchModal"
          >
            Receive Stock Batch
          </v-btn>
          <v-btn
            color="amber-darken-3"
            variant="tonal"
            prepend-icon="mdi-scale-balance"
            class="font-weight-medium text-none"
            @click="openAdjustmentModal"
          >
            Adjust Stock
          </v-btn>
        </div>
      </div>
    </v-card>

    <!-- Inventory Tabs -->
    <v-tabs v-model="activeTab" color="primary" class="mb-4 bg-white rounded-lg elevation-1 px-2">
      <v-tab value="products" prepend-icon="mdi-pill">Product Catalog</v-tab>
      <v-tab value="batches" prepend-icon="mdi-layers-outline">Active Batches (FEFO)</v-tab>
      <v-tab value="expiry" prepend-icon="mdi-alert-circle-outline">
        <v-badge
          v-if="expirySummary.critical_30.count + expirySummary.expired.count > 0"
          :content="expirySummary.critical_30.count + expirySummary.expired.count"
          color="error"
          inline
        >
          Expiry Risk & Alerts
        </v-badge>
        <span v-else>Expiry Risk & Alerts</span>
      </v-tab>
      <v-tab value="adjustments" prepend-icon="mdi-history">Stock Adjustments</v-tab>
      <v-tab value="movements" prepend-icon="mdi-swap-horizontal-bold">Movement Ledger</v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <!-- TAB 1: PRODUCT CATALOG -->
      <v-window-item value="products">
        <v-card class="elevation-1 rounded-lg pa-4">
          <v-row class="mb-2" density="compact">
            <v-col cols="12" sm="6" md="4">
              <v-text-field
                v-model="productSearch"
                label="Search by Name, Generic, SKU, or Barcode"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @update:model-value="onSearchInput"
                @click:clear="onSearchClear"
              ></v-text-field>
            </v-col>
            <v-col cols="12" sm="6" md="3">
              <v-select
                v-model="selectedCategory"
                :items="categories"
                item-title="name"
                item-value="id"
                label="Filter by Category"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @update:model-value="loadProducts"
              ></v-select>
            </v-col>
            <v-col cols="12" sm="6" md="3" class="d-flex align-center">
              <v-checkbox
                v-model="filterLowStock"
                label="Low Stock Only"
                color="error"
                density="compact"
                hide-details
                @update:model-value="loadProducts"
              ></v-checkbox>
            </v-col>
            <v-col cols="12" sm="6" md="2" class="text-end">
              <v-btn icon="mdi-refresh" variant="text" @click="loadProducts" :loading="loadingProducts"></v-btn>
            </v-col>
          </v-row>

          <v-table hover density="comfortable">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th class="font-weight-bold">Product Name</th>
                <th class="font-weight-bold">Generic Name</th>
                <th class="font-weight-bold">SKU / Barcode</th>
                <th class="font-weight-bold">Category</th>
                <th class="font-weight-bold text-end">Buy Price</th>
                <th class="font-weight-bold text-end">Sell Price</th>
                <th class="font-weight-bold text-center">Branch Stock</th>
                <th class="font-weight-bold text-center">Reorder Level</th>
                <th class="font-weight-bold text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="products.length === 0">
                <td colspan="9" class="text-center text-grey py-6">No products found.</td>
              </tr>
              <tr v-for="p in products" :key="p.id">
                <td class="font-weight-medium">
                  {{ p.name }}
                  <v-chip
                    v-if="p.is_prescription_required"
                    size="x-small"
                    color="blue"
                    class="ml-1 text-no-wrap"
                    variant="outlined"
                    title="Prescription Required: Doctor prescription required to dispense"
                  >
                    Rx
                  </v-chip>
                  <v-chip
                    v-if="p.is_controlled"
                    size="x-small"
                    color="red"
                    class="ml-1 text-no-wrap"
                    variant="flat"
                    title="Controlled Substance: Strict auditing and register logging required"
                  >
                    Controlled
                  </v-chip>
                </td>
                <td class="text-caption text-grey-darken-2" :title="p.generic_name">{{ p.generic_name }}</td>
                <td>
                  <span class="text-caption font-mono font-weight-bold" :title="'SKU: ' + p.sku">{{ p.sku }}</span>
                  <div v-if="p.barcode" class="text-caption text-grey" :title="'Barcode: ' + p.barcode">{{ p.barcode }}</div>
                </td>
                <td>{{ p.category?.name }}</td>
                <td class="text-end font-mono">KES {{ Number(p.purchase_price).toFixed(2) }}</td>
                <td class="text-end font-mono font-weight-bold text-teal-darken-3">KES {{ Number(p.selling_price).toFixed(2) }}</td>
                <td class="text-center">
                  <v-chip
                    :color="(p.branch_stock ?? 0) <= p.reorder_level ? 'error' : 'success'"
                    size="small"
                    class="font-weight-bold text-no-wrap"
                    :title="'Branch Stock: ' + Number(p.branch_stock ?? 0) + ' ' + (p.unit?.short_name || 'units') + ((p.branch_stock ?? 0) <= p.reorder_level ? ' (Low Stock Alert: Below reorder level of ' + p.reorder_level + ')' : ' (Adequate)')"
                  >
                    {{ Number(p.branch_stock ?? 0) }} {{ p.unit?.short_name || 'units' }}
                  </v-chip>
                </td>
                <td class="text-center text-caption" :title="'Reorder threshold: ' + p.reorder_level + ' units'">{{ p.reorder_level }}</td>
                <td class="text-center">
                  <v-chip
                    size="x-small"
                    :color="p.is_active ? 'success' : 'grey'"
                    variant="tonal"
                    class="text-no-wrap"
                    :title="p.is_active ? 'Active: Available for sales and dispensing' : 'Inactive: Hidden from POS'"
                  >
                    {{ p.is_active ? 'Active' : 'Inactive' }}
                  </v-chip>
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <!-- TAB 2: ACTIVE BATCHES (FEFO) -->
      <v-window-item value="batches">
        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="d-flex justify-space-between align-center mb-3">
            <div class="text-subtitle-1 font-weight-bold text-grey-darken-3">
              FEFO Priority Order (First Expiry, First Out)
            </div>
            <v-btn icon="mdi-refresh" variant="text" size="small" @click="loadBatches" :loading="loadingBatches"></v-btn>
          </div>

          <v-table hover density="comfortable">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th class="font-weight-bold">Batch #</th>
                <th class="font-weight-bold">Medicine</th>
                <th class="font-weight-bold">Supplier</th>
                <th class="font-weight-bold">Expiry Date</th>
                <th class="font-weight-bold text-center">Days Left</th>
                <th class="font-weight-bold text-end">Available Qty</th>
                <th class="font-weight-bold text-end">Unit Cost</th>
                <th class="font-weight-bold text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="batches.length === 0">
                <td colspan="8" class="text-center text-grey py-6">No batches found for this branch.</td>
              </tr>
              <tr v-for="b in batches" :key="b.id">
                <td class="font-mono font-weight-bold text-teal-darken-4" :title="'Batch #' + b.batch_number">{{ b.batch_number }}</td>
                <td>
                  <div class="font-weight-medium" :title="b.product?.name">{{ b.product?.name }}</div>
                  <div class="text-caption text-grey" :title="'SKU: ' + b.product?.sku">{{ b.product?.sku }}</div>
                </td>
                <td class="text-caption" :title="'Supplier: ' + (b.supplier?.name || 'Standard Intake')">{{ b.supplier?.name || 'Standard Intake' }}</td>
                <td class="font-weight-medium" :title="'Expiry date: ' + b.expiry_date">{{ b.expiry_date }}</td>
                <td class="text-center">
                  <v-chip
                    size="small"
                    :color="getExpiryChipColor(b.days_until_expiry)"
                    class="font-weight-bold text-no-wrap"
                    :title="'Expires in ' + b.days_until_expiry + ' days (' + b.expiry_date + ')'"
                  >
                    {{ b.days_until_expiry <= 0 ? 'EXPIRED' : `${b.days_until_expiry} days` }}
                  </v-chip>
                </td>
                <td class="text-end font-weight-bold font-mono">{{ Number(b.quantity_remaining) }}</td>
                <td class="text-end font-mono">KES {{ Number(b.purchase_price_per_unit).toFixed(2) }}</td>
                <td class="text-center">
                  <v-chip
                    size="x-small"
                    :color="b.status === 'active' ? 'success' : 'error'"
                    variant="flat"
                    class="text-no-wrap font-weight-bold"
                    :title="'Batch status: ' + b.status.toUpperCase()"
                  >
                    {{ b.status.toUpperCase() }}
                  </v-chip>
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <!-- TAB 3: EXPIRY RISK & QUARANTINE -->
      <v-window-item value="expiry">
        <v-row class="mb-4" density="compact">
          <v-col cols="12" sm="6" md="3">
            <v-card
              class="pa-4 rounded-lg border-s-lg border-red elevation-1 h-100 d-flex flex-column justify-center cursor-default"
              style="min-height: 104px;"
            >
              <div class="text-caption font-weight-bold text-red">EXPIRED BATCHES (BLOCKED)</div>
              <div class="text-h4 font-weight-bold text-red mt-1">{{ expirySummary.expired.count }}</div>
              <div class="text-caption text-grey">
                {{ Number(expirySummary.expired.units) }} units | KES {{ Number(expirySummary.expired.value_at_risk).toFixed(2) }} at risk
              </div>
            </v-card>
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-card
              class="pa-4 rounded-lg border-s-lg border-deep-orange elevation-1 h-100 d-flex flex-column justify-center cursor-default"
              style="min-height: 104px;"
            >
              <div class="text-caption font-weight-bold text-deep-orange">CRITICAL (<= 30 DAYS)</div>
              <div class="text-h4 font-weight-bold text-deep-orange mt-1">{{ expirySummary.critical_30.count }}</div>
              <div class="text-caption text-grey">
                {{ Number(expirySummary.critical_30.units) }} units | KES {{ Number(expirySummary.critical_30.value_at_risk).toFixed(2) }} at risk
              </div>
            </v-card>
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-card
              class="pa-4 rounded-lg border-s-lg border-amber elevation-1 h-100 d-flex flex-column justify-center cursor-default"
              style="min-height: 104px;"
            >
              <div class="text-caption font-weight-bold text-amber-darken-3">WARNING (31 - 60 DAYS)</div>
              <div class="text-h4 font-weight-bold text-amber-darken-3 mt-1">{{ expirySummary.warning_60.count }}</div>
              <div class="text-caption text-grey">
                {{ Number(expirySummary.warning_60.units) }} units | KES {{ Number(expirySummary.warning_60.value_at_risk).toFixed(2) }} at risk
              </div>
            </v-card>
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-card
              class="pa-4 rounded-lg border-s-lg border-teal elevation-1 h-100 d-flex flex-column justify-center cursor-default"
              style="min-height: 104px;"
            >
              <div class="text-caption font-weight-bold text-teal">SAFE STOCK (> 90 DAYS)</div>
              <div class="text-h4 font-weight-bold text-teal mt-1">{{ expirySummary.safe.count }}</div>
              <div class="text-caption text-grey">
                {{ Number(expirySummary.safe.units) }} units safe
              </div>
            </v-card>
          </v-col>
        </v-row>

        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="d-flex justify-space-between align-center mb-3">
            <div class="text-subtitle-1 font-weight-bold text-grey-darken-3">
              Expiry Risk Watchlist (Financial Value-at-Risk)
            </div>
            <v-btn icon="mdi-refresh" variant="text" size="small" @click="loadExpiryReport" title="Refresh expiry watchlist"></v-btn>
          </div>

          <v-table hover density="comfortable">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th class="font-weight-bold">Batch #</th>
                <th class="font-weight-bold">Medicine</th>
                <th class="font-weight-bold">Expiry Date</th>
                <th class="font-weight-bold text-center">Days Remaining</th>
                <th class="font-weight-bold text-end">Quantity Remaining</th>
                <th class="font-weight-bold text-end">Purchase Cost</th>
                <th class="font-weight-bold text-end">Value at Risk (KES)</th>
                <th class="font-weight-bold text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="b in expiryBatches" :key="b.id">
                <td class="font-mono font-weight-bold" :title="'Batch #' + b.batch_number">{{ b.batch_number }}</td>
                <td>
                  <div class="font-weight-medium" :title="b.product_name">{{ b.product_name }}</div>
                  <div class="text-caption text-grey" :title="'SKU: ' + b.sku">{{ b.sku }}</div>
                </td>
                <td class="font-weight-medium" :title="'Expiry date: ' + b.expiry_date">{{ b.expiry_date }}</td>
                <td class="text-center">
                  <v-chip
                    size="small"
                    :color="getExpiryChipColor(b.days_until_expiry)"
                    class="font-weight-bold text-no-wrap"
                    :title="'Batch ' + b.batch_number + ': ' + (b.days_until_expiry <= 0 ? 'Expired' : b.days_until_expiry + ' days until expiry')"
                  >
                    {{ b.days_until_expiry <= 0 ? 'EXPIRED' : `${b.days_until_expiry} days` }}
                  </v-chip>
                </td>
                <td class="text-end font-mono font-weight-bold">{{ Number(b.quantity_remaining) }}</td>
                <td class="text-end font-mono">KES {{ Number(b.purchase_price_per_unit).toFixed(2) }}</td>
                <td class="text-end font-mono font-weight-bold text-red-darken-2" :title="'Financial value at risk: KES ' + Number(b.value_at_risk).toFixed(2)">
                  KES {{ Number(b.value_at_risk).toFixed(2) }}
                </td>
                <td class="text-center">
                  <v-btn
                    v-if="b.days_until_expiry <= 0"
                    size="x-small"
                    color="error"
                    variant="tonal"
                    @click="quarantineExpiredBatch(b)"
                  >
                    Disposal Scrap
                  </v-btn>
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <!-- TAB 4: STOCK ADJUSTMENTS -->
      <v-window-item value="adjustments">
        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="d-flex justify-space-between align-center mb-3">
            <div class="text-subtitle-1 font-weight-bold text-grey-darken-3">Audit-Controlled Stock Adjustments</div>
            <v-btn icon="mdi-refresh" variant="text" size="small" @click="loadAdjustments"></v-btn>
          </div>

          <v-table hover density="comfortable">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th class="font-weight-bold">Adjustment #</th>
                <th class="font-weight-bold">Reason</th>
                <th class="font-weight-bold">Initiated By</th>
                <th class="font-weight-bold">Date & Time</th>
                <th class="font-weight-bold">Items Count</th>
                <th class="font-weight-bold">Status</th>
                <th class="font-weight-bold">Notes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="adjustments.length === 0">
                <td colspan="7" class="text-center text-grey py-6">No stock adjustments recorded.</td>
              </tr>
              <tr v-for="adj in adjustments" :key="adj.id">
                <td class="font-mono font-weight-bold text-teal-darken-4" :title="'Adjustment #' + adj.adjustment_number">{{ adj.adjustment_number }}</td>
                <td class="text-capitalize" :title="adj.reason_type.replace(/_/g, ' ')">{{ adj.reason_type.replace(/_/g, ' ') }}</td>
                <td :title="'Recorded by: ' + adj.user?.name">{{ adj.user?.name }}</td>
                <td class="text-caption font-mono">{{ formatIsoDateTime(adj.created_at) }}</td>
                <td class="font-weight-bold">{{ adj.items?.length || 0 }} items</td>
                <td>
                  <v-chip
                    size="x-small"
                    color="success"
                    variant="flat"
                    class="text-no-wrap font-weight-bold"
                    :title="'Adjustment status: ' + adj.status.toUpperCase()"
                  >
                    {{ adj.status.toUpperCase() }}
                  </v-chip>
                </td>
                <td class="text-caption text-grey" :title="adj.notes || '-'">{{ adj.notes || '-' }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>

      <!-- TAB 5: STOCK MOVEMENT LEDGER -->
      <v-window-item value="movements">
        <v-card class="elevation-1 rounded-lg pa-4">
          <div class="d-flex justify-space-between align-center mb-3">
            <div class="text-subtitle-1 font-weight-bold text-grey-darken-3">Immutable Stock Movement Ledger</div>
            <v-btn icon="mdi-refresh" variant="text" size="small" @click="loadMovements" title="Refresh movement ledger"></v-btn>
          </div>

          <v-table hover density="comfortable">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th class="font-weight-bold">Timestamp</th>
                <th class="font-weight-bold">Medicine</th>
                <th class="font-weight-bold">Batch #</th>
                <th class="font-weight-bold">Movement Type</th>
                <th class="font-weight-bold text-end">Quantity Delta</th>
                <th class="font-weight-bold text-end">Balance After</th>
                <th class="font-weight-bold text-end">Unit Cost</th>
                <th class="font-weight-bold">User</th>
                <th class="font-weight-bold">Reason / Reference</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="movements.length === 0">
                <td colspan="9" class="text-center text-grey py-6">No stock movements found.</td>
              </tr>
              <tr v-for="m in movements" :key="m.id">
                <td class="text-caption font-mono">{{ formatIsoDateTime(m.created_at) }}</td>
                <td>
                  <div class="font-weight-medium" :title="m.product?.name">{{ m.product?.name }}</div>
                  <div class="text-caption text-grey" :title="'SKU: ' + m.product?.sku">{{ m.product?.sku }}</div>
                </td>
                <td class="font-mono font-weight-bold" :title="'Batch: ' + m.batch?.batch_number">{{ m.batch?.batch_number }}</td>
                <td>
                  <v-chip
                    size="x-small"
                    :color="getMovementColor(m.movement_type)"
                    variant="tonal"
                    class="text-capitalize text-no-wrap font-weight-bold"
                    :title="'Stock transaction type: ' + m.movement_type.replace(/_/g, ' ')"
                  >
                    {{ m.movement_type.replace(/_/g, ' ') }}
                  </v-chip>
                </td>
                <td class="text-end font-mono font-weight-bold" :class="m.quantity_delta > 0 ? 'text-success' : 'text-error'">
                  {{ m.quantity_delta > 0 ? '+' : '' }}{{ Number(m.quantity_delta) }}
                </td>
                <td class="text-end font-mono font-weight-bold">{{ Number(m.balance_after) }}</td>
                <td class="text-end font-mono">KES {{ Number(m.unit_cost).toFixed(2) }}</td>
                <td class="text-caption">{{ m.user?.name || 'System' }}</td>
                <td class="text-caption text-grey">{{ m.reason || '-' }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-window-item>
    </v-window>

    <!-- MODAL 1: ADD NEW PRODUCT -->
    <v-dialog v-model="showAddProductModal" max-width="650px">
      <v-card class="pa-4 rounded-lg">
        <v-card-title class="text-h6 font-weight-bold text-teal-darken-3">
          Register New Pharmaceutical Product
        </v-card-title>
        <v-card-text>
          <v-form @submit.prevent="saveNewProduct">
            <v-row density="compact">
              <v-col cols="12" md="6">
                <v-text-field v-model="newProduct.name" label="Product Commercial Name *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="newProduct.generic_name" label="Active Molecule / Generic Name *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="newProduct.sku" label="Internal SKU Code *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="newProduct.barcode" label="Scannable Barcode (EAN/UPC)" variant="outlined" density="compact"></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-select v-model="newProduct.category_id" :items="categories" item-title="name" item-value="id" label="Category *" variant="outlined" density="compact" required></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-select v-model="newProduct.unit_id" :items="units" item-title="name" item-value="id" label="Unit of Measurement *" variant="outlined" density="compact" required></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model.number="newProduct.purchase_price" type="number" step="0.01" label="Purchase Price (KES) *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model.number="newProduct.selling_price" type="number" step="0.01" label="Selling Price (KES) *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model.number="newProduct.reorder_level" type="number" label="Reorder Level Alert *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model.number="newProduct.max_stock_level" type="number" label="Maximum Stock Level" variant="outlined" density="compact"></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-checkbox v-model="newProduct.is_prescription_required" label="Prescription Required (Rx)" density="compact" color="primary" hide-details></v-checkbox>
              </v-col>
              <v-col cols="12" md="6">
                <v-checkbox v-model="newProduct.is_controlled" label="Controlled Substance" density="compact" color="error" hide-details></v-checkbox>
              </v-col>
            </v-row>
            <div class="d-flex justify-end mt-4">
              <v-btn variant="text" @click="showAddProductModal = false" class="mr-2">Cancel</v-btn>
              <v-btn color="primary" type="submit" :loading="savingProduct">Save Product</v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL 2: RECEIVE STOCK BATCH -->
    <v-dialog v-model="showReceiveBatchModal" max-width="600px">
      <v-card class="pa-4 rounded-lg">
        <v-card-title class="text-h6 font-weight-bold text-teal-darken-3">
          Receive Stock Batch Intake
        </v-card-title>
        <v-card-text>
          <v-form @submit.prevent="saveReceivedBatch">
            <v-row density="compact">
              <v-col cols="12">
                <v-select
                  v-model="newBatch.product_id"
                  :items="products"
                  item-title="name"
                  item-value="id"
                  label="Select Medicine *"
                  variant="outlined"
                  density="compact"
                  required
                ></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="newBatch.batch_number" label="Manufacturer Batch Number *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="newBatch.expiry_date" type="date" label="Expiry Date *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model.number="newBatch.quantity" type="number" step="0.01" label="Quantity Received *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model.number="newBatch.purchase_price_per_unit" type="number" step="0.01" label="Purchase Cost / Unit (KES) *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model.number="newBatch.selling_price_per_unit" type="number" step="0.01" label="Selling Price / Unit (KES) *" variant="outlined" density="compact" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-select v-model="newBatch.supplier_id" :items="suppliers" item-title="name" item-value="id" label="Supplier" variant="outlined" density="compact"></v-select>
              </v-col>
            </v-row>
            <div class="d-flex justify-end mt-4">
              <v-btn variant="text" @click="showReceiveBatchModal = false" class="mr-2">Cancel</v-btn>
              <v-btn color="primary" type="submit" :loading="savingBatch">Complete Ingestion</v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL 3: STOCK ADJUSTMENT -->
    <v-dialog v-model="showAdjustmentModal" max-width="600px">
      <v-card class="pa-4 rounded-lg">
        <v-card-title class="text-h6 font-weight-bold text-amber-darken-4">
          Execute Stock Adjustment
        </v-card-title>
        <v-card-text>
          <v-form @submit.prevent="saveAdjustment">
            <v-row density="compact">
              <v-col cols="12">
                <v-select
                  v-model="adjForm.product_batch_id"
                  :items="batches"
                  :item-title="item => `${item.batch_number} - ${item.product?.name} (Current: ${item.quantity_remaining})`"
                  item-value="id"
                  label="Select Batch to Correct *"
                  variant="outlined"
                  density="compact"
                  required
                ></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="adjForm.reason_type"
                  :items="adjustmentReasons"
                  item-title="title"
                  item-value="value"
                  label="Adjustment Reason *"
                  variant="outlined"
                  density="compact"
                  required
                ></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="adjForm.new_quantity"
                  type="number"
                  step="0.01"
                  label="Verified Physical Quantity *"
                  variant="outlined"
                  density="compact"
                  required
                ></v-text-field>
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="adjForm.notes"
                  label="Mandatory Audit Notes *"
                  rows="2"
                  variant="outlined"
                  density="compact"
                  required
                ></v-textarea>
              </v-col>
            </v-row>
            <div class="d-flex justify-end mt-4">
              <v-btn variant="text" @click="showAdjustmentModal = false" class="mr-2">Cancel</v-btn>
              <v-btn color="amber-darken-3" type="submit" :loading="savingAdjustment">Apply Correction</v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import { useNotificationStore } from '@/stores/notification';
import { formatIsoDateTime } from '@/utils/formatters';

const notify = useNotificationStore();

const activeTab = ref('products');
const productSearch = ref('');
const selectedCategory = ref(null);
const filterLowStock = ref(false);

const loadingProducts = ref(false);
const loadingBatches = ref(false);

const products = ref([]);
const categories = ref([]);
const units = ref([]);
const suppliers = ref([]);
const batches = ref([]);
const adjustments = ref([]);
const movements = ref([]);

const expirySummary = ref({
  expired: { count: 0, units: 0, value_at_risk: 0 },
  critical_30: { count: 0, units: 0, value_at_risk: 0 },
  warning_60: { count: 0, units: 0, value_at_risk: 0 },
  alert_90: { count: 0, units: 0, value_at_risk: 0 },
  safe: { count: 0, units: 0, value_at_risk: 0 },
  total_value_at_risk: 0,
});
const expiryBatches = ref([]);

// Modals
const showAddProductModal = ref(false);
const showReceiveBatchModal = ref(false);
const showAdjustmentModal = ref(false);

const savingProduct = ref(false);
const savingBatch = ref(false);
const savingAdjustment = ref(false);

const newProduct = ref({
  name: '',
  generic_name: '',
  sku: '',
  barcode: '',
  category_id: null,
  unit_id: null,
  pack_size: 1,
  purchase_price: 0,
  selling_price: 0,
  reorder_level: 10,
  max_stock_level: 500,
  is_prescription_required: false,
  is_controlled: false,
});

const newBatch = ref({
  product_id: null,
  supplier_id: null,
  batch_number: '',
  expiry_date: '',
  quantity: 100,
  purchase_price_per_unit: 10,
  selling_price_per_unit: 15,
});

const adjForm = ref({
  product_batch_id: null,
  reason_type: 'inventory_count_discrepancy',
  new_quantity: 0,
  notes: '',
});

const adjustmentReasons = [
  { title: 'Physical Count Discrepancy', value: 'inventory_count_discrepancy' },
  { title: 'Damaged / Spillage Scrap', value: 'damaged' },
  { title: 'Expired Medicine Disposal', value: 'expired' },
  { title: 'Theft / Missing Units', value: 'missing_theft' },
  { title: 'Internal Emergency Clinic Use', value: 'internal_use' },
];

function getExpiryChipColor(days) {
  if (days <= 0) return 'error';
  if (days <= 30) return 'deep-orange-darken-2';
  if (days <= 60) return 'amber-darken-3';
  if (days <= 90) return 'yellow-darken-3';
  return 'teal';
}

function getMovementColor(type) {
  if (type.includes('receipt') || type.includes('addition') || type.includes('restock')) return 'success';
  if (type.includes('damaged') || type.includes('expired')) return 'error';
  return 'info';
}

let searchDebounceTimer = null;

function onSearchInput() {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    loadProducts();
  }, 300);
}

function onSearchClear() {
  productSearch.value = '';
  loadProducts();
}

async function loadProducts() {
  loadingProducts.value = true;
  try {
    const params = {};
    if (productSearch.value && productSearch.value.trim()) {
      params.search = productSearch.value.trim();
    }
    if (selectedCategory.value) {
      params.category_id = selectedCategory.value;
    }
    if (filterLowStock.value) {
      params.low_stock = true;
    }

    const res = await api.get('/products', { params });
    products.value = res.data.data.data;
  } catch (e) {
    notify.error('Failed to load products');
  } finally {
    loadingProducts.value = false;
  }
}

async function loadBatches() {
  loadingBatches.value = true;
  try {
    const res = await api.get('/batches');
    batches.value = res.data.data.data;
  } catch (e) {
    notify.error('Failed to load batches');
  } finally {
    loadingBatches.value = false;
  }
}

async function loadExpiryReport() {
  try {
    const res = await api.get('/batches/expiry-alerts');
    expirySummary.value = res.data.data.summary;
    expiryBatches.value = res.data.data.batches;
  } catch (e) {
    notify.error('Failed to load expiry report');
  }
}

async function loadAdjustments() {
  try {
    const res = await api.get('/stock-adjustments');
    adjustments.value = res.data.data.data;
  } catch (e) {
    notify.error('Failed to load adjustments');
  }
}

async function loadMovements() {
  try {
    const res = await api.get('/stock-movements');
    movements.value = res.data.data.data;
  } catch (e) {
    notify.error('Failed to load movements');
  }
}

async function loadMasterData() {
  try {
    const [catRes, unitRes, supRes] = await Promise.all([
      api.get('/categories'),
      api.get('/units'),
      api.get('/suppliers'),
    ]);
    categories.value = catRes.data.data;
    units.value = unitRes.data.data;
    suppliers.value = supRes.data.data;
  } catch (e) {
    notify.error('Failed to load master metadata');
  }
}

function openAddProductModal() {
  showAddProductModal.value = true;
}

function openReceiveBatchModal() {
  showReceiveBatchModal.value = true;
}

function openAdjustmentModal() {
  showAdjustmentModal.value = true;
}

async function saveNewProduct() {
  savingProduct.value = true;
  try {
    await api.post('/products', newProduct.value);
    notify.success('Medicine created successfully');
    showAddProductModal.value = false;
    loadProducts();
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to save product');
  } finally {
    savingProduct.value = false;
  }
}

async function saveReceivedBatch() {
  savingBatch.value = true;
  try {
    await api.post('/batches', newBatch.value);
    notify.success('Batch received and added to inventory');
    showReceiveBatchModal.value = false;
    loadBatches();
    loadProducts();
    loadExpiryReport();
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to receive batch');
  } finally {
    savingBatch.value = false;
  }
}

async function saveAdjustment() {
  savingAdjustment.value = true;
  try {
    await api.post('/stock-adjustments', {
      reason_type: adjForm.value.reason_type,
      notes: adjForm.value.notes,
      items: [
        {
          product_batch_id: adjForm.value.product_batch_id,
          new_quantity: adjForm.value.new_quantity,
          notes: adjForm.value.notes,
        },
      ],
    });
    notify.success('Stock correction applied');
    showAdjustmentModal.value = false;
    loadBatches();
    loadProducts();
    loadAdjustments();
    loadMovements();
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to adjust stock');
  } finally {
    savingAdjustment.value = false;
  }
}

function quarantineExpiredBatch(b) {
  adjForm.value.product_batch_id = b.id;
  adjForm.value.reason_type = 'expired';
  adjForm.value.new_quantity = 0;
  adjForm.value.notes = 'Expired batch written off for disposal scrap';
  showAdjustmentModal.value = true;
}

onMounted(() => {
  loadMasterData();
  loadProducts();
  loadBatches();
  loadExpiryReport();
  loadAdjustments();
  loadMovements();
});
</script>
