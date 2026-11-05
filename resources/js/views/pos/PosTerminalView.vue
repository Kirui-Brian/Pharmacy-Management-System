<template>
  <div class="pos-container">
    <v-row density="compact" class="fill-height">
      <!-- LEFT COLUMN: MEDICINE SEARCH & PRODUCT CATALOG -->
      <v-col cols="12" md="7" lg="8">
        <v-card class="elevation-1 rounded-lg pa-4 mb-3" color="white">
          <v-row density="compact" align="center">
            <v-col cols="12" sm="8">
              <v-text-field
                ref="searchInputRef"
                v-model="searchQuery"
                placeholder="Scan Barcode or Search by Name, Generic, SKU... (F2)"
                prepend-inner-icon="mdi-barcode-scan"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @update:model-value="onSearchInput"
                @keydown.enter="handleBarcodeOrFirstHit"
                @click:clear="onSearchClear"
                class="bg-grey-lighten-5 rounded"
              >
                <template v-slot:append-inner>
                  <v-chip size="x-small" color="grey-lighten-2" class="text-caption font-weight-bold">F2</v-chip>
                </template>
              </v-text-field>
            </v-col>
            <v-col cols="12" sm="4" class="d-flex justify-end align-center">
              <v-btn
                v-if="heldCarts.length > 0"
                color="amber-darken-3"
                variant="tonal"
                size="small"
                prepend-icon="mdi-pause-circle-outline"
                @click="showHeldCartsModal = true"
                class="mr-2 text-none"
              >
                Held ({{ heldCarts.length }})
              </v-btn>
              <v-btn
                icon="mdi-refresh"
                size="small"
                variant="text"
                @click="loadProducts"
                :loading="loadingProducts"
              ></v-btn>
            </v-col>
          </v-row>

          <!-- CATEGORY PILLS -->
          <div class="d-flex overflow-x-auto py-2 ga-2">
            <v-chip
              size="small"
              :variant="selectedCategory === null ? 'flat' : 'outlined'"
              color="primary"
              @click="selectCategory(null)"
              class="font-weight-medium flex-shrink-0"
              title="Show all product categories"
            >
              All Categories
            </v-chip>
            <v-chip
              v-for="cat in categories"
              :key="cat.id"
              size="small"
              :variant="selectedCategory === cat.id ? 'flat' : 'outlined'"
              color="primary"
              @click="selectCategory(cat.id)"
              class="font-weight-medium flex-shrink-0"
              :title="'Filter by ' + cat.name"
            >
              {{ cat.name }}
            </v-chip>
          </div>
        </v-card>

        <!-- PRODUCT GRID -->
        <v-card class="elevation-1 rounded-lg pa-4" style="min-height: 520px; max-height: calc(100vh - 220px); overflow-y: auto;">
          <v-progress-linear v-if="loadingProducts" indeterminate color="primary" class="mb-2"></v-progress-linear>

          <v-row density="compact" v-if="filteredProducts.length > 0">
            <v-col
              v-for="product in filteredProducts"
              :key="product.id"
              cols="12"
              sm="6"
              md="6"
              lg="4"
              xl="3"
            >
              <v-card
                variant="outlined"
                class="product-card pa-3 rounded-lg h-100 d-flex flex-column justify-space-between cursor-pointer transition-swing"
                style="min-height: 200px;"
                :class="{ 'opacity-50 pointer-events-none': (product.branch_stock ?? 0) <= 0 }"
                @click="addToCart(product)"
                hover
              >
                <div>
                  <div class="d-flex justify-space-between align-center mb-1">
                    <span
                      class="text-caption font-mono text-grey-darken-1 text-truncate mr-2 font-weight-medium"
                      style="font-size: 11px !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 58%;"
                      :title="'SKU: ' + product.sku + (product.barcode ? ' | Barcode: ' + product.barcode : '')"
                    >
                      {{ product.sku }}
                    </span>
                    <v-chip
                      :color="(product.branch_stock ?? 0) > 0 ? 'success' : 'error'"
                      size="x-small"
                      variant="tonal"
                      class="font-weight-bold flex-shrink-0 px-2"
                      style="font-size: 11px !important; height: 22px;"
                      :title="'Inventory Stock: ' + Number(product.branch_stock ?? 0) + ' ' + (product.unit?.short_name || 'units') + ' available in branch'"
                    >
                      <v-icon start icon="mdi-package-variant-closed" size="12" class="mr-1" v-if="(product.branch_stock ?? 0) > 0"></v-icon>
                      <span v-if="(product.branch_stock ?? 0) > 0">{{ Number(product.branch_stock ?? 0) }}</span>
                      <span v-else>Out of Stock</span>
                    </v-chip>
                  </div>
                  <div
                    class="font-weight-bold text-body-2 mb-1"
                    :title="product.name"
                    style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.25; min-height: 36px; word-break: break-word;"
                  >
                    {{ product.name }}
                  </div>
                  <div
                    class="text-caption text-grey text-truncate d-block"
                    style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                    :title="'Generic: ' + (product.generic_name || 'N/A')"
                  >
                    {{ product.generic_name || 'Generic' }}
                  </div>
                </div>

                <div class="mt-auto pt-2 border-t d-flex justify-space-between align-center">
                  <div style="min-width: 0;" class="mr-1">
                    <span class="text-caption text-grey d-block text-truncate" style="font-size: 10px; line-height: 1.1;">Retail Price</span>
                    <span
                      class="font-weight-bold text-teal-darken-3 font-mono text-body-2 text-truncate d-block"
                      :title="'Selling Price: KES ' + Number(product.selling_price).toFixed(2)"
                    >
                      KES {{ Number(product.selling_price).toFixed(2) }}
                    </span>
                  </div>
                  <v-btn
                    icon="mdi-plus"
                    size="small"
                    color="primary"
                    variant="tonal"
                    density="comfortable"
                    class="flex-shrink-0"
                    :disabled="(product.branch_stock ?? 0) <= 0"
                    @click.stop="addToCart(product)"
                    :title="'Add ' + product.name + ' to Cart'"
                  ></v-btn>
                </div>
              </v-card>
            </v-col>
          </v-row>

          <div v-else-if="!loadingProducts" class="text-center py-12 text-grey">
            <v-icon icon="mdi-magnify" size="48" class="mb-2 text-grey-lighten-1"></v-icon>
            <div class="text-body-1 font-weight-medium">No medicines found</div>
            <div class="text-caption">Scan barcode or try a different search keyword</div>
          </div>
        </v-card>
      </v-col>

      <!-- RIGHT COLUMN: SHOPPING CART & CHECKOUT -->
      <v-col cols="12" md="5" lg="4">
        <v-card class="elevation-1 rounded-lg pa-4 d-flex flex-column" style="height: calc(100vh - 120px);">
          <!-- CUSTOMER HEADER -->
          <div class="d-flex justify-space-between align-center pb-2 border-b">
            <div class="d-flex align-center flex-grow-1 mr-2">
              <v-icon icon="mdi-account" color="primary" class="mr-2"></v-icon>
              <div class="flex-grow-1">
                <v-select
                  v-model="selectedCustomer"
                  :items="customerOptions"
                  item-title="title"
                  item-value="id"
                  variant="plain"
                  density="compact"
                  hide-details
                  class="font-weight-bold text-body-2"
                ></v-select>
              </div>
            </div>
            <v-btn
              icon="mdi-account-plus"
              size="small"
              variant="text"
              color="primary"
              @click="showAddCustomerModal = true"
              title="Add New Customer"
            ></v-btn>
          </div>

          <!-- CUSTOMER CREDIT BADGE (if applicable) -->
          <div
            v-if="selectedCustomerData && selectedCustomerData.customer_category === 'credit'"
            class="bg-blue-lighten-5 px-3 py-1 text-caption text-blue-darken-3 d-flex justify-space-between rounded mb-1"
            :title="'Credit limit: KES ' + Number(selectedCustomerData.credit_limit).toFixed(2) + ' | Current debt: KES ' + Number(selectedCustomerData.outstanding_balance).toFixed(2)"
          >
            <span>Credit Limit: <b>KES {{ Number(selectedCustomerData.credit_limit).toFixed(2) }}</b></span>
            <span>Balance: <b>KES {{ Number(selectedCustomerData.outstanding_balance).toFixed(2) }}</b></span>
          </div>

          <!-- CART ITEMS LIST -->
          <div class="cart-items-container flex-grow-1 overflow-y-auto my-2 pr-1">
            <div v-if="cart.length === 0" class="text-center py-12 text-grey">
              <v-icon icon="mdi-cart-outline" size="48" class="text-grey-lighten-2 mb-2"></v-icon>
              <div class="text-body-2 font-weight-medium">Cart is empty</div>
              <div class="text-caption">Scan products or click items to add</div>
            </div>

            <div v-else>
              <div
                v-for="(item, index) in cart"
                :key="item.product_id"
                class="cart-item pa-2 mb-2 rounded bg-grey-lighten-5 border d-flex flex-column"
              >
                <div class="d-flex justify-space-between align-start mb-1">
                  <div class="font-weight-medium text-body-2 text-truncate-2 mr-2" :title="item.name">
                    {{ item.name }}
                    <v-chip
                      v-if="item.is_prescription_required"
                      size="x-small"
                      color="blue"
                      class="ml-1"
                      variant="outlined"
                      title="Prescription Required (Doctor's Rx required)"
                    >
                      Rx
                    </v-chip>
                  </div>
                  <v-btn
                    icon="mdi-close"
                    size="x-small"
                    variant="text"
                    color="grey-darken-1"
                    @click="removeFromCart(index)"
                  ></v-btn>
                </div>

                <div class="d-flex justify-space-between align-center mt-1">
                  <!-- QUANTITY CONTROL -->
                  <div class="d-flex align-center ga-1">
                    <v-btn
                      icon="mdi-minus"
                      size="x-small"
                      variant="tonal"
                      color="grey"
                      @click="decreaseQty(index)"
                    ></v-btn>
                    <input
                      type="number"
                      v-model.number="item.quantity"
                      min="1"
                      :max="item.available_stock"
                      class="qty-input text-center font-weight-bold"
                    />
                    <v-btn
                      icon="mdi-plus"
                      size="x-small"
                      variant="tonal"
                      color="grey"
                      :disabled="item.quantity >= item.available_stock"
                      @click="increaseQty(index)"
                    ></v-btn>
                    <span class="text-caption text-grey ml-1">{{ item.unit_name }}</span>
                  </div>

                  <!-- PRICE & LINE TOTAL -->
                  <div class="text-end">
                    <div class="font-weight-bold font-mono text-teal-darken-3">
                      KES {{ (item.quantity * item.unit_selling_price).toFixed(2) }}
                    </div>
                    <div class="text-caption text-grey font-mono">
                      @ {{ Number(item.unit_selling_price).toFixed(2) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- CART TOTALS & ACTIONS -->
          <div class="border-t pt-2">
            <div class="d-flex justify-space-between text-body-2 text-grey-darken-2 mb-1">
              <span>Subtotal ({{ totalItemsCount }} items)</span>
              <span class="font-mono font-weight-medium">KES {{ cartSubtotal.toFixed(2) }}</span>
            </div>

            <!-- DISCOUNT LINE -->
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption text-grey-darken-1">Discount</span>
              <div class="d-flex align-center ga-1" style="max-width: 140px;">
                <v-select
                  v-model="discountType"
                  :items="[{ title: 'None', value: 'none' }, { title: '%', value: 'percentage' }, { title: 'KES', value: 'fixed' }]"
                  item-title="title"
                  item-value="value"
                  variant="underlined"
                  density="compact"
                  hide-details
                  style="width: 50px;"
                ></v-select>
                <input
                  v-if="discountType !== 'none'"
                  type="number"
                  v-model.number="discountValue"
                  class="discount-input font-mono text-end"
                  placeholder="0"
                />
              </div>
            </div>

            <!-- GRAND TOTAL -->
            <div class="d-flex justify-space-between align-center py-2 px-3 bg-teal-lighten-5 rounded mb-3">
              <span class="text-subtitle-1 font-weight-bold text-teal-darken-4">Total Amount</span>
              <span class="text-h6 font-weight-bold text-teal-darken-4 font-mono">
                KES {{ cartGrandTotal.toFixed(2) }}
              </span>
            </div>

            <!-- ACTION BUTTONS -->
            <v-row density="compact">
              <v-col cols="6">
                <v-btn
                  block
                  variant="outlined"
                  color="grey-darken-1"
                  prepend-icon="mdi-pause"
                  :disabled="cart.length === 0"
                  @click="holdCurrentCart"
                  class="text-none"
                >
                  Hold (F8)
                </v-btn>
              </v-col>
              <v-col cols="6">
                <v-btn
                  block
                  variant="tonal"
                  color="error"
                  prepend-icon="mdi-delete-sweep-outline"
                  :disabled="cart.length === 0"
                  @click="clearCart"
                  class="text-none"
                >
                  Clear (Esc)
                </v-btn>
              </v-col>
              <v-col cols="12" class="mt-1">
                <v-btn
                  block
                  color="primary"
                  size="large"
                  prepend-icon="mdi-cash-register"
                  :disabled="cart.length === 0"
                  @click="openCheckoutModal"
                  class="text-none font-weight-bold"
                >
                  Pay & Complete (F4)
                </v-btn>
              </v-col>
            </v-row>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- MODAL 1: CHECKOUT & PAYMENT -->
    <v-dialog v-model="showCheckoutModal" max-width="650px" persistent>
      <v-card class="pa-4 rounded-lg">
        <v-card-title class="d-flex justify-space-between align-center pb-2 border-b">
          <div class="d-flex align-center">
            <span class="text-h6 font-weight-bold">POS Checkout & Payment</span>
            <v-chip color="teal" size="default" variant="flat" class="font-mono font-weight-bold ml-2">
              KES {{ cartGrandTotal.toFixed(2) }}
            </v-chip>
          </div>
          <v-btn
            icon="mdi-close"
            variant="text"
            size="small"
            color="grey-darken-2"
            @click="showCheckoutModal = false"
            title="Close"
          ></v-btn>
        </v-card-title>

        <v-card-text class="pt-4">
          <!-- SPLIT PAYMENT TOGGLE -->
          <div class="d-flex justify-space-between align-center mb-3 pa-2 bg-grey-lighten-4 rounded">
            <div>
              <div class="font-weight-medium text-body-2">Split Payment</div>
              <div class="text-caption text-grey">Settle one sale using multiple payment methods</div>
            </div>
            <v-switch v-model="isSplitPayment" color="primary" hide-details density="compact"></v-switch>
          </div>

          <!-- SINGLE PAYMENT MODE -->
          <div v-if="!isSplitPayment">
            <v-row density="compact" class="mb-3">
              <v-col cols="3" v-for="method in paymentMethods" :key="method.value">
                <v-card
                  variant="outlined"
                  class="pa-2 text-center rounded cursor-pointer transition-swing"
                  :class="{ 'bg-primary-lighten-5 border-primary border-md': selectedPaymentMethod === method.value }"
                  @click="selectedPaymentMethod = method.value"
                  :title="'Select ' + method.title + ' payment method'"
                >
                  <v-icon :icon="method.icon" :color="selectedPaymentMethod === method.value ? 'primary' : 'grey'"></v-icon>
                  <div class="text-caption font-weight-bold mt-1">{{ method.title }}</div>
                </v-card>
              </v-col>
            </v-row>

            <!-- CASH TENDERED -->
            <div v-if="selectedPaymentMethod === 'cash'" class="mb-3">
              <v-text-field
                v-model.number="singleTendered"
                label="Cash Amount Tendered (KES)"
                variant="outlined"
                density="compact"
                type="number"
                prefix="KES"
                autofocus
                hide-details
              ></v-text-field>
              <div class="d-flex justify-space-between align-center mt-2 px-2 py-1 bg-green-lighten-5 rounded text-green-darken-3 font-weight-bold">
                <span>Change to Return:</span>
                <span class="font-mono text-h6">KES {{ Math.max(0, (singleTendered - cartGrandTotal)).toFixed(2) }}</span>
              </div>
            </div>

            <!-- MPESA / STK PUSH PROMPT -->
            <div v-if="selectedPaymentMethod === 'mpesa'" class="mb-3">
              <v-card variant="outlined" class="pa-3 bg-green-lighten-5 border-green mb-2">
                <div class="text-caption font-weight-bold text-green-darken-4 mb-2 d-flex align-center">
                  <v-icon icon="mdi-cellphone-wireless" color="green-darken-3" class="mr-1"></v-icon>
                  LIPA NA M-PESA ONLINE PROMPT (STK PUSH)
                </div>
                <v-row density="compact" align="center">
                  <v-col cols="12" sm="7">
                    <v-text-field
                      v-model="mpesaPhone"
                      label="Customer Phone Number *"
                      placeholder="e.g. 0712345678"
                      variant="outlined"
                      density="compact"
                      bg-color="white"
                      hide-details
                    ></v-text-field>
                  </v-col>
                  <v-col cols="12" sm="5">
                    <v-btn
                      block
                      color="green-darken-2"
                      prepend-icon="mdi-send"
                      class="text-none font-weight-bold"
                      style="height: 40px;"
                      :loading="sendingMpesaPrompt"
                      @click="triggerMpesaPrompt"
                    >
                      Prompt Phone
                    </v-btn>
                  </v-col>
                </v-row>
              </v-card>

              <v-text-field
                v-model="singleRef"
                label="M-Pesa Transaction Code *"
                placeholder="e.g. QHJ872619A (Auto-filled on prompt confirmation)"
                variant="outlined"
                density="compact"
                hide-details
              ></v-text-field>
            </div>

            <!-- CREDIT SALE NOTE -->
            <div v-if="selectedPaymentMethod === 'credit'" class="mb-3">
              <v-alert type="warning" variant="tonal" density="compact" class="text-caption">
                This transaction will be recorded on customer credit (Accounts Receivable).
              </v-alert>
            </div>
          </div>

          <!-- SPLIT PAYMENT MODE -->
          <div v-else>
            <div class="text-caption font-weight-bold mb-2">Split Amounts Across Methods:</div>
            <v-row density="compact" v-for="(split, sIdx) in splitPayments" :key="sIdx" class="mb-2 align-center">
              <v-col cols="4">
                <v-select
                  v-model="split.payment_method"
                  :items="paymentMethods"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="compact"
                  hide-details
                ></v-select>
              </v-col>
              <v-col cols="4">
                <v-text-field
                  v-model.number="split.amount"
                  type="number"
                  label="Amount (KES)"
                  variant="outlined"
                  density="compact"
                  hide-details
                  prefix="KES"
                ></v-text-field>
              </v-col>
              <v-col cols="3">
                <v-text-field
                  v-if="split.payment_method === 'mpesa' || split.payment_method === 'card'"
                  v-model="split.reference_number"
                  label="Ref #"
                  placeholder="Txn ID"
                  variant="outlined"
                  density="compact"
                  hide-details
                ></v-text-field>
              </v-col>
              <v-col cols="1" class="text-center">
                <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="removeSplitRow(sIdx)" :disabled="splitPayments.length <= 1"></v-btn>
              </v-col>
            </v-row>

            <v-btn size="small" variant="text" color="primary" prepend-icon="mdi-plus" @click="addSplitRow" class="text-none">
              Add Payment Line
            </v-btn>

            <div class="d-flex justify-space-between align-center mt-3 pt-2 border-t font-weight-bold">
              <span>Total Settle: KES {{ splitTotalAllocated.toFixed(2) }}</span>
              <span :class="splitRemainingToAllocate === 0 ? 'text-success' : 'text-error'">
                {{ splitRemainingToAllocate === 0 ? '✓ Balanced' : `Remaining: KES ${splitRemainingToAllocate.toFixed(2)}` }}
              </span>
            </div>
          </div>
        </v-card-text>

        <v-card-actions class="pa-4 border-t">
          <v-btn color="grey-darken-2" variant="outlined" class="text-none" @click="showCheckoutModal = false" :disabled="processingSale">Cancel</v-btn>
          <v-spacer></v-spacer>
          <v-btn
            color="primary"
            variant="flat"
            size="large"
            :loading="processingSale"
            :disabled="isSplitPayment && splitRemainingToAllocate !== 0"
            @click="submitCheckout"
            class="px-6 text-none font-weight-bold"
          >
            Confirm & Print Receipt
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- MODAL 2: RECEIPT PREVIEW & PRINT (Dedicated Modular Component) -->
    <ReceiptModal
      v-model="showReceiptModal"
      :sale="completedSale"
      @new-sale="startNewSale"
    />

    <!-- MODAL 3: QUICK ADD CUSTOMER -->
    <v-dialog v-model="showAddCustomerModal" max-width="500px">
      <v-card class="pa-4 rounded-lg">
        <v-card-title class="font-weight-bold text-h6 d-flex justify-space-between align-center pb-2 border-b">
          <span>Register New Customer</span>
          <v-btn
            icon="mdi-close"
            variant="text"
            size="small"
            color="grey-darken-2"
            @click="showAddCustomerModal = false"
            title="Close"
          ></v-btn>
        </v-card-title>
        <v-card-text class="pt-3">
          <v-form @submit.prevent="saveNewCustomer">
            <v-text-field v-model="newCustomer.name" label="Full Name *" variant="outlined" density="compact" required class="mb-2"></v-text-field>
            <v-text-field v-model="newCustomer.phone" label="Phone Number" variant="outlined" density="compact" class="mb-2"></v-text-field>
            <v-select
              v-model="newCustomer.customer_category"
              :items="['regular', 'credit', 'insurance', 'corporate']"
              label="Category *"
              variant="outlined"
              density="compact"
              required
              class="mb-2"
            ></v-select>
            <v-text-field
              v-if="newCustomer.customer_category === 'credit'"
              v-model.number="newCustomer.credit_limit"
              type="number"
              label="Credit Limit (KES)"
              variant="outlined"
              density="compact"
              class="mb-2"
            ></v-text-field>
            <div class="d-flex justify-end ga-2 mt-3 pt-2 border-t">
              <v-btn color="grey-darken-2" variant="outlined" class="text-none" @click="showAddCustomerModal = false">Cancel</v-btn>
              <v-btn color="primary" type="submit" :loading="savingCustomer" class="text-none font-weight-bold">Save Customer</v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL 4: HELD CARTS -->
    <v-dialog v-model="showHeldCartsModal" max-width="500px">
      <v-card class="pa-4 rounded-lg">
        <v-card-title class="font-weight-bold text-h6 d-flex justify-space-between align-center pb-2 border-b">
          <span>Held Transactions</span>
          <v-btn
            icon="mdi-close"
            variant="text"
            size="small"
            color="grey-darken-2"
            @click="showHeldCartsModal = false"
            title="Close"
          ></v-btn>
        </v-card-title>
        <v-card-text class="pt-3">
          <v-list v-if="heldCarts.length > 0">
            <v-list-item
              v-for="(h, idx) in heldCarts"
              :key="idx"
              class="border rounded mb-2 pa-2"
            >
              <template v-slot:prepend>
                <v-icon icon="mdi-cart-pause" color="amber-darken-3"></v-icon>
              </template>
              <v-list-item-title class="font-weight-bold">
                {{ h.customerName || 'Walk-in Customer' }}
              </v-list-item-title>
              <v-list-item-subtitle class="text-caption">
                {{ h.items.length }} items | KES {{ h.total.toFixed(2) }} | {{ formatIsoDateTime(h.time) }}
              </v-list-item-subtitle>
              <template v-slot:append>
                <v-btn size="small" color="primary" variant="tonal" @click="resumeHeldCart(idx)" class="mr-1 text-none">Resume</v-btn>
                <v-btn size="small" color="error" variant="text" icon="mdi-delete" @click="heldCarts.splice(idx, 1)"></v-btn>
              </template>
            </v-list-item>
          </v-list>
          <div v-else class="text-center py-6 text-grey">No held transactions</div>
        </v-card-text>
        <v-card-actions class="border-t">
          <v-spacer></v-spacer>
          <v-btn color="grey-darken-2" variant="outlined" class="text-none" @click="showHeldCartsModal = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- MODAL 5: M-PESA STK PUSH PROMPT DIALOG (Dedicated Modular Component) -->
    <MpesaPromptModal
      v-model="showMpesaPromptModal"
      :amount="cartGrandTotal"
      :initial-phone="mpesaPhone || selectedCustomer?.phone"
      @confirmed="onMpesaConfirmed"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '@/services/api';
import { useNotificationStore } from '@/stores/notification';
import { formatIsoDateTime } from '@/utils/formatters';
import MpesaPromptModal from '@/components/pos/MpesaPromptModal.vue';
import ReceiptModal from '@/components/pos/ReceiptModal.vue';

const notify = useNotificationStore();

// Search & Catalog
const searchInputRef = ref(null);
const searchQuery = ref('');
const products = ref([]);
const categories = ref([]);
const selectedCategory = ref(null);
const loadingProducts = ref(false);

// Cart
const cart = ref([]);
const selectedCustomer = ref(null);
const customers = ref([]);
const discountType = ref('none');
const discountValue = ref(0);

// Held Carts
const heldCarts = ref([]);
const showHeldCartsModal = ref(false);

// Checkout
const showCheckoutModal = ref(false);
const processingSale = ref(false);
const selectedPaymentMethod = ref('cash');
const singleTendered = ref(0);
const singleRef = ref('');
const isSplitPayment = ref(false);
const splitPayments = ref([
  { payment_method: 'cash', amount: 0, reference_number: '' },
  { payment_method: 'mpesa', amount: 0, reference_number: '' },
]);

// M-Pesa STK Push
const mpesaPhone = ref('');
const sendingMpesaPrompt = ref(false);
const showMpesaPromptModal = ref(false);
const mpesaPromptStatus = ref('initiating');
const mpesaCheckoutRequestId = ref('');
const mpesaCountdown = ref(25);

// Receipt
const showReceiptModal = ref(false);
const completedSale = ref(null);

// Customer Modal
const showAddCustomerModal = ref(false);
const savingCustomer = ref(false);
const newCustomer = ref({
  name: '',
  phone: '',
  customer_category: 'regular',
  credit_limit: 0,
});

const paymentMethods = [
  { title: 'Cash', value: 'cash', icon: 'mdi-cash' },
  { title: 'M-Pesa', value: 'mpesa', icon: 'mdi-cellphone' },
  { title: 'Card', value: 'card', icon: 'mdi-credit-card-outline' },
  { title: 'Credit', value: 'credit', icon: 'mdi-account-clock-outline' },
];

// Computed
const filteredProducts = computed(() => {
  if (!selectedCategory.value) return products.value;
  return products.value.filter(p => p.category_id === selectedCategory.value);
});

const customerOptions = computed(() => {
  const list = [{ id: null, title: 'Walk-in Customer' }];
  customers.value.forEach(c => {
    list.push({
      id: c.id,
      title: `${c.name} (${c.customer_category.toUpperCase()})`,
    });
  });
  return list;
});

const selectedCustomerData = computed(() => {
  if (!selectedCustomer.value) return null;
  return customers.value.find(c => c.id === selectedCustomer.value);
});

const totalItemsCount = computed(() => {
  return cart.value.reduce((sum, item) => sum + item.quantity, 0);
});

const cartSubtotal = computed(() => {
  return cart.value.reduce((sum, item) => sum + (item.quantity * item.unit_selling_price), 0);
});

const cartGrandTotal = computed(() => {
  let sub = cartSubtotal.value;
  if (discountType.value === 'percentage' && discountValue.value > 0) {
    sub -= (sub * discountValue.value) / 100;
  } else if (discountType.value === 'fixed' && discountValue.value > 0) {
    sub -= discountValue.value;
  }
  return Math.max(0, sub);
});

const splitTotalAllocated = computed(() => {
  return splitPayments.value.reduce((sum, s) => sum + (Number(s.amount) || 0), 0);
});

const splitRemainingToAllocate = computed(() => {
  return Math.round((cartGrandTotal.value - splitTotalAllocated.value) * 100) / 100;
});

// Search & Catalog Methods
let searchDebounceTimer = null;
function onSearchInput() {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    loadProducts();
  }, 250);
}

function onSearchClear() {
  searchQuery.value = '';
  loadProducts();
}

function selectCategory(catId) {
  selectedCategory.value = catId;
}

async function loadProducts() {
  loadingProducts.value = true;
  try {
    const params = {};
    if (searchQuery.value && searchQuery.value.trim()) {
      params.q = searchQuery.value.trim();
    }
    const res = await api.get('/pos/products', { params });
    products.value = res.data.data;
  } catch (e) {
    notify.error('Failed to load products');
  } finally {
    loadingProducts.value = false;
  }
}

async function loadCategories() {
  try {
    const res = await api.get('/categories');
    categories.value = res.data.data;
  } catch (e) {}
}

async function loadCustomers() {
  try {
    const res = await api.get('/customers');
    customers.value = res.data.data.data;
  } catch (e) {}
}

// Cart Methods
function addToCart(product) {
  const existing = cart.value.find(i => i.product_id === product.id);
  const maxStock = Number(product.branch_stock ?? 0);

  if (existing) {
    if (existing.quantity < maxStock) {
      existing.quantity++;
    } else {
      notify.warning(`Maximum available stock reached for ${product.name}`);
    }
  } else {
    cart.value.push({
      product_id: product.id,
      name: product.name,
      sku: product.sku,
      unit_name: product.unit?.short_name || 'unit',
      unit_selling_price: Number(product.selling_price),
      quantity: 1,
      available_stock: maxStock,
      is_prescription_required: product.is_prescription_required,
    });
  }
}

function handleBarcodeOrFirstHit() {
  if (products.value.length === 1 && (products.value[0].branch_stock ?? 0) > 0) {
    addToCart(products.value[0]);
    searchQuery.value = '';
    loadProducts();
  }
}

function increaseQty(index) {
  if (cart.value[index].quantity < cart.value[index].available_stock) {
    cart.value[index].quantity++;
  }
}

function decreaseQty(index) {
  if (cart.value[index].quantity > 1) {
    cart.value[index].quantity--;
  } else {
    removeFromCart(index);
  }
}

function removeFromCart(index) {
  cart.value.splice(index, 1);
}

function clearCart() {
  cart.value = [];
  discountType.value = 'none';
  discountValue.value = 0;
}

// Hold Cart
function holdCurrentCart() {
  if (cart.value.length === 0) return;
  heldCarts.value.push({
    items: [...cart.value],
    customer: selectedCustomer.value,
    customerName: selectedCustomerData.value?.name || 'Walk-in',
    discountType: discountType.value,
    discountValue: discountValue.value,
    total: cartGrandTotal.value,
    time: new Date().toISOString(),
  });
  clearCart();
  notify.info('Cart held in background.');
}

function resumeHeldCart(idx) {
  const h = heldCarts.value[idx];
  cart.value = [...h.items];
  selectedCustomer.value = h.customer;
  discountType.value = h.discountType;
  discountValue.value = h.discountValue;
  heldCarts.value.splice(idx, 1);
  showHeldCartsModal.value = false;
  notify.success('Cart resumed.');
}

// Checkout Methods
function openCheckoutModal() {
  singleTendered.value = cartGrandTotal.value;
  singleRef.value = '';
  isSplitPayment.value = false;
  splitPayments.value = [
    { payment_method: 'cash', amount: cartGrandTotal.value, reference_number: '' },
    { payment_method: 'mpesa', amount: 0, reference_number: '' },
  ];
  showCheckoutModal.value = true;
}

function addSplitRow() {
  splitPayments.value.push({ payment_method: 'cash', amount: 0, reference_number: '' });
}

function removeSplitRow(idx) {
  splitPayments.value.splice(idx, 1);
}

async function submitCheckout() {
  processingSale.value = true;
  try {
    const payload = {
      customer_id: selectedCustomer.value,
      discount_type: discountType.value,
      discount_rate: discountType.value === 'percentage' ? discountValue.value : 0,
      discount_amount: discountType.value === 'fixed' ? discountValue.value : 0,
      items: cart.value.map(i => ({
        product_id: i.product_id,
        quantity: i.quantity,
        unit_selling_price: i.unit_selling_price,
      })),
      payments: [],
    };

    if (!isSplitPayment.value) {
      payload.payments.push({
        payment_method: selectedPaymentMethod.value,
        amount: cartGrandTotal.value,
        amount_tendered: selectedPaymentMethod.value === 'cash' ? singleTendered.value : cartGrandTotal.value,
        reference_number: selectedPaymentMethod.value === 'mpesa' ? singleRef.value : null,
      });
    } else {
      payload.payments = splitPayments.value
        .filter(s => Number(s.amount) > 0)
        .map(s => ({
          payment_method: s.payment_method,
          amount: Number(s.amount),
          amount_tendered: Number(s.amount),
          reference_number: s.reference_number || null,
        }));
    }

    const res = await api.post('/pos/checkout', payload);
    completedSale.value = res.data.data;
    showCheckoutModal.value = false;
    showReceiptModal.value = true;
    notify.success('Sale completed successfully!');
  } catch (e) {
    const msg = e.response?.data?.message || 'Checkout failed. Please check stock and credit limits.';
    notify.error(msg);
  } finally {
    processingSale.value = false;
  }
}

function startNewSale() {
  showReceiptModal.value = false;
  completedSale.value = null;
  clearCart();
  loadProducts();
  if (searchInputRef.value) {
    searchInputRef.value.focus();
  }
}

// M-Pesa STK Push
function triggerMpesaPrompt() {
  if (!mpesaPhone.value && selectedCustomerData.value?.phone) {
    mpesaPhone.value = selectedCustomerData.value.phone;
  }
  showMpesaPromptModal.value = true;
}

function onMpesaConfirmed(payload) {
  singleRef.value = payload.transactionCode;
  mpesaPhone.value = payload.phone;
  notify.success(`M-Pesa payment captured (${payload.transactionCode})`);
}

// Add Customer
async function saveNewCustomer() {
  savingCustomer.value = true;
  try {
    const res = await api.post('/customers', newCustomer.value);
    customers.value.push(res.data.data);
    selectedCustomer.value = res.data.data.id;
    showAddCustomerModal.value = false;
    notify.success('Customer registered and selected.');
  } catch (e) {
    notify.error('Failed to register customer');
  } finally {
    savingCustomer.value = false;
  }
}

// Keyboard shortcuts listener
function handleKeydown(e) {
  if (e.key === 'F2') {
    e.preventDefault();
    if (searchInputRef.value) searchInputRef.value.focus();
  } else if (e.key === 'F4') {
    e.preventDefault();
    if (cart.value.length > 0 && !showCheckoutModal.value) openCheckoutModal();
  } else if (e.key === 'F8') {
    e.preventDefault();
    holdCurrentCart();
  } else if (e.key === 'Escape') {
    if (showCheckoutModal.value) showCheckoutModal.value = false;
    else if (showReceiptModal.value) showReceiptModal.value = false;
    else clearCart();
  }
}

onMounted(() => {
  loadProducts();
  loadCategories();
  loadCustomers();
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
});
</script>

<style>
@media print {
  /* Global print rules: hide everything except printable receipt */
  html, body {
    background: #fff !important;
  }
  body * {
    visibility: hidden !important;
  }
  #printable-receipt, #printable-receipt * {
    visibility: visible !important;
  }
  #printable-receipt {
    position: fixed !important;
    left: 0 !important;
    top: 0 !important;
    width: 76mm !important;
    padding: 0 !important;
    margin: 0 !important;
    border: none !important;
    background: white !important;
    color: black !important;
    z-index: 999999 !important;
  }
  .v-overlay-container, .v-navigation-drawer, .v-app-bar, .v-card-actions {
    display: none !important;
  }
}
</style>
<style scoped>
.pos-container {
  height: calc(100vh - 100px);
}
.product-card {
  transition: all 0.2s ease-in-out;
  border-color: #E2E8F0;
}
.product-card:hover {
  border-color: #0D9488;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.cart-item {
  border-color: #E2E8F0;
}
.qty-input {
  width: 44px;
  background: #fff;
  border: 1px solid #CBD5E1;
  border-radius: 4px;
  padding: 2px 4px;
  font-size: 13px;
}
.discount-input {
  width: 70px;
  background: #fff;
  border: 1px solid #CBD5E1;
  border-radius: 4px;
  padding: 2px 6px;
  font-size: 12px;
}
.border-t-dashed {
  border-top: 1px dashed #666;
}
.receipt-table th, .receipt-table td {
  padding: 2px 0;
}
@media print {
  body * {
    visibility: hidden;
  }
  #printable-receipt, #printable-receipt * {
    visibility: visible;
  }
  #printable-receipt {
    position: absolute;
    left: 0;
    top: 0;
    width: 80mm;
  }
}
</style>
