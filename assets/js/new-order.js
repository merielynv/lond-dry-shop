/**
 * LOND Dry Shop — New Order & Customer Management Interface
 *
 * Handles:
 *  - AJAX customer lookup / auto-fill (Requirement #5)
 *  - Building a multi-line service cart (Requirement #4: transaction
 *    processing input stage)
 *  - Client-side validation before review (Requirement #6) - the
 *    server in new_order_action.php re-checks everything regardless,
 *    since this can be bypassed
 *  - The "double-check" confirmation modal
 *
 * NOTE: prices shown here are for staff convenience only. The
 * authoritative price used to actually save the order is re-read from
 * the database server-side - this script never sends prices, only
 * service_id + quantity pairs (see buildItemsPayload()).
 */
(function () {
  'use strict';

  var cartItems = []; // { service_id, name, unit_type, quantity, unit_price, subtotal }

  /* =========================================================
     ELEMENT REFERENCES
     ========================================================= */

  var customerSearch = document.getElementById('customerSearch');
  var lookupResults = document.getElementById('lookupResults');
  var customerFoundHint = document.getElementById('customerFoundHint');

  var fullNameInput = document.getElementById('fullName');
  var phoneInput = document.getElementById('phoneNumber');
  var addressInput = document.getElementById('address');
  var phoneError = document.getElementById('phoneError');

  var serviceSelect = document.getElementById('serviceSelect');
  var quantityInput = document.getElementById('quantityInput');
  var quantityLabel = document.getElementById('quantityLabel');
  var addItemBtn = document.getElementById('addItemBtn');
  var cartBody = document.getElementById('cartBody');

  var paymentMethodSelect = document.getElementById('paymentMethod');
  var referenceField = document.getElementById('referenceField');
  var referenceNumberInput = document.getElementById('referenceNumber');
  var amountPaidInput = document.getElementById('amountPaid');
  var paidError = document.getElementById('paidError');

  var summaryTotal = document.getElementById('summaryTotal');
  var summaryChange = document.getElementById('summaryChange');

  var reviewOrderBtn = document.getElementById('reviewOrderBtn');
  var newOrderForm = document.getElementById('newOrderForm');
  var itemsJsonField = document.getElementById('itemsJson');

  var confirmModal = document.getElementById('confirmModal');
  var confirmCustomerName = document.getElementById('confirmCustomerName');
  var confirmCustomerPhone = document.getElementById('confirmCustomerPhone');
  var confirmItemsList = document.getElementById('confirmItemsList');
  var confirmTotal = document.getElementById('confirmTotal');
  var confirmPaymentMethod = document.getElementById('confirmPaymentMethod');
  var confirmAmountPaid = document.getElementById('confirmAmountPaid');
  var confirmChange = document.getElementById('confirmChange');
  var confirmEditBtn = document.getElementById('confirmEditBtn');
  var confirmProceedBtn = document.getElementById('confirmProceedBtn');

  function peso(amount) {
    var value = isNaN(amount) ? 0 : amount;
    return '₱' + value.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
  }

  /* =========================================================
     CUSTOMER LOOKUP (AJAX)
     ========================================================= */

  var lookupTimer = null;

  function runLookup(term) {
    fetch('customer_lookup.php?term=' + encodeURIComponent(term))
      .then(function (response) { return response.json(); })
      .then(function (data) {
        renderLookupResults(data.customers || []);
      })
      .catch(function () {
        renderLookupResults([]);
      });
  }

  function renderLookupResults(customers) {
    lookupResults.innerHTML = '';

    if (customers.length === 0) {
      lookupResults.innerHTML = '<p class="lookup-empty">No matching customer. You can encode them as new below.</p>';
      lookupResults.hidden = false;
      return;
    }

    customers.forEach(function (customer) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'lookup-result';
      button.innerHTML =
        '<strong></strong><span></span>';
      button.querySelector('strong').textContent = customer.full_name;
      button.querySelector('span').textContent =
        customer.phone_number + (customer.address ? ' · ' + customer.address : '');

      button.addEventListener('click', function () {
        fullNameInput.value = customer.full_name;
        phoneInput.value = customer.phone_number;
        addressInput.value = customer.address || '';
        lookupResults.hidden = true;
        customerSearch.value = '';
        customerFoundHint.hidden = false;
        validatePhone();
      });

      lookupResults.appendChild(button);
    });

    lookupResults.hidden = false;
  }

  if (customerSearch) {
    customerSearch.addEventListener('input', function () {
      var term = customerSearch.value.trim();
      customerFoundHint.hidden = true;

      clearTimeout(lookupTimer);

      if (term.length < 2) {
        lookupResults.hidden = true;
        return;
      }

      lookupTimer = setTimeout(function () {
        runLookup(term);
      }, 250);
    });

    document.addEventListener('click', function (event) {
      if (!lookupResults.contains(event.target) && event.target !== customerSearch) {
        lookupResults.hidden = true;
      }
    });
  }

  function validatePhone() {
    var value = phoneInput.value.trim();
    var pattern = /^[0-9+\-\s]{7,20}$/;

    if (value === '') {
      phoneError.textContent = '';
      return false;
    }
    if (!pattern.test(value)) {
      phoneError.textContent = 'Use 7-20 digits (spaces, +, - are okay).';
      phoneError.classList.add('is-error');
      return false;
    }
    phoneError.textContent = '';
    phoneError.classList.remove('is-error');
    return true;
  }

  if (phoneInput) {
    phoneInput.addEventListener('blur', validatePhone);
    phoneInput.addEventListener('input', function () {
      phoneError.textContent = '';
      phoneError.classList.remove('is-error');
    });
  }

  /* =========================================================
     SERVICE PICKER: dynamic quantity label/step per unit type
     ========================================================= */

  function refreshQuantityFieldForSelectedService() {
    if (!serviceSelect || serviceSelect.options.length === 0) return;

    var option = serviceSelect.options[serviceSelect.selectedIndex];
    var unit = option.getAttribute('data-unit');

    if (unit === 'per_kg') {
      quantityLabel.textContent = 'Weight in kilograms (kg) *';
      quantityInput.step = '0.1';
      quantityInput.min = '0.1';
    } else if (unit === 'per_piece') {
      quantityLabel.textContent = 'Number of pieces *';
      quantityInput.step = '1';
      quantityInput.min = '1';
    } else if (unit === 'per_load') {
      quantityLabel.textContent = 'Number of loads *';
      quantityInput.step = '1';
      quantityInput.min = '1';
    } else {
      quantityLabel.textContent = 'Quantity *';
      quantityInput.step = '0.1';
      quantityInput.min = '0.1';
    }
  }

  if (serviceSelect) {
    serviceSelect.addEventListener('change', refreshQuantityFieldForSelectedService);
    refreshQuantityFieldForSelectedService();
  }

  /* =========================================================
     CART BUILDING
     ========================================================= */

  function addItemToCart() {
    if (!serviceSelect || serviceSelect.options.length === 0) return;

    var option = serviceSelect.options[serviceSelect.selectedIndex];
    var serviceId = parseInt(option.value, 10);
    var quantity = parseFloat(quantityInput.value);

    if (!serviceId || isNaN(quantity) || quantity <= 0) {
      quantityInput.focus();
      return;
    }

    var unitPrice = parseFloat(option.getAttribute('data-price')) || 0;
    var name = option.getAttribute('data-name');
    var unit = option.getAttribute('data-unit');

    var existing = cartItems.find(function (item) { return item.service_id === serviceId; });

    if (existing) {
      existing.quantity += quantity;
      existing.subtotal = round2(existing.quantity * existing.unit_price);
    } else {
      cartItems.push({
        service_id: serviceId,
        name: name,
        unit_type: unit,
        quantity: quantity,
        unit_price: unitPrice,
        subtotal: round2(quantity * unitPrice)
      });
    }

    quantityInput.value = '';
    renderCart();
    recalcTotals();
  }

  function removeItemFromCart(index) {
    cartItems.splice(index, 1);
    renderCart();
    recalcTotals();
  }

  function round2(value) {
    return Math.round(value * 100) / 100;
  }

  function unitSuffix(unitType) {
    if (unitType === 'per_kg') return 'kg';
    if (unitType === 'per_piece') return 'pc(s)';
    if (unitType === 'per_load') return 'load(s)';
    return '';
  }

  function renderCart() {
    if (!cartBody) return;
    cartBody.innerHTML = '';

    if (cartItems.length === 0) {
      var emptyRow = document.createElement('tr');
      emptyRow.innerHTML = '<td colspan="5" class="cart-empty">No services added yet.</td>';
      cartBody.appendChild(emptyRow);
      return;
    }

    cartItems.forEach(function (item, index) {
      var row = document.createElement('tr');

      var nameCell = document.createElement('td');
      nameCell.textContent = item.name;

      var qtyCell = document.createElement('td');
      qtyCell.textContent = item.quantity + ' ' + unitSuffix(item.unit_type);

      var priceCell = document.createElement('td');
      priceCell.textContent = peso(item.unit_price);

      var subtotalCell = document.createElement('td');
      subtotalCell.textContent = peso(item.subtotal);

      var actionCell = document.createElement('td');
      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'cart-remove';
      removeBtn.setAttribute('aria-label', 'Remove ' + item.name);
      removeBtn.textContent = '×';
      removeBtn.addEventListener('click', function () { removeItemFromCart(index); });
      actionCell.appendChild(removeBtn);

      row.appendChild(nameCell);
      row.appendChild(qtyCell);
      row.appendChild(priceCell);
      row.appendChild(subtotalCell);
      row.appendChild(actionCell);
      cartBody.appendChild(row);
    });
  }

  if (addItemBtn) {
    addItemBtn.addEventListener('click', addItemToCart);
  }

  /* =========================================================
     PAYMENT CALCULATIONS
     ========================================================= */

  function cartTotal() {
    return round2(cartItems.reduce(function (sum, item) { return sum + item.subtotal; }, 0));
  }

  function recalcTotals() {
    var total = cartTotal();
    var amountPaid = parseFloat(amountPaidInput.value) || 0;
    var change = round2(amountPaid - total);

    summaryTotal.textContent = peso(total);
    summaryChange.textContent = peso(change > 0 ? change : 0);

    if (amountPaidInput.value !== '' && amountPaid < total) {
      paidError.textContent = 'This is less than the order total (' + peso(total) + ').';
      paidError.classList.add('is-error');
    } else {
      paidError.textContent = '';
      paidError.classList.remove('is-error');
    }
  }

  function toggleReferenceField() {
    var method = paymentMethodSelect.value;
    referenceField.hidden = !(method === 'Card' || method === 'Online');
  }

  if (amountPaidInput) {
    amountPaidInput.addEventListener('input', recalcTotals);
  }
  if (paymentMethodSelect) {
    paymentMethodSelect.addEventListener('change', toggleReferenceField);
    toggleReferenceField();
  }

  /* =========================================================
     REVIEW & CONFIRM ("DOUBLE-CHECK") MODAL
     ========================================================= */

  function validateBeforeReview() {
    var errors = [];

    if (fullNameInput.value.trim() === '') {
      errors.push('Please enter the customer\'s full name.');
    }
    if (!validatePhone() || phoneInput.value.trim() === '') {
      errors.push('Please enter a valid phone number.');
    }
    if (cartItems.length === 0) {
      errors.push('Please add at least one service to the order.');
    }

    var total = cartTotal();
    var amountPaid = parseFloat(amountPaidInput.value) || 0;

    if (amountPaidInput.value === '' || amountPaid < total) {
      errors.push('Amount paid must cover the full order total (pay-first policy).');
    }

    var method = paymentMethodSelect.value;
    if ((method === 'Card' || method === 'Online') && referenceNumberInput.value.trim() === '') {
      errors.push('A reference number is required for Card or Online payments.');
    }

    if (errors.length > 0) {
      alert(errors.join('\n'));
      return false;
    }
    return true;
  }

  function openConfirmModal() {
    confirmCustomerName.textContent = fullNameInput.value.trim();
    confirmCustomerPhone.textContent = phoneInput.value.trim();

    confirmItemsList.innerHTML = '';
    cartItems.forEach(function (item) {
      var li = document.createElement('li');
      li.innerHTML =
        '<span></span><strong></strong>';
      li.querySelector('span').textContent =
        item.name + ' — ' + item.quantity + ' ' + unitSuffix(item.unit_type);
      li.querySelector('strong').textContent = peso(item.subtotal);
      confirmItemsList.appendChild(li);
    });

    var total = cartTotal();
    var amountPaid = parseFloat(amountPaidInput.value) || 0;
    var change = round2(amountPaid - total);

    confirmTotal.textContent = peso(total);
    confirmPaymentMethod.textContent = paymentMethodSelect.value;
    confirmAmountPaid.textContent = peso(amountPaid);
    confirmChange.textContent = peso(change > 0 ? change : 0);

    confirmModal.classList.add('is-open');
    confirmModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeConfirmModal() {
    confirmModal.classList.remove('is-open');
    confirmModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  if (reviewOrderBtn) {
    reviewOrderBtn.addEventListener('click', function () {
      if (validateBeforeReview()) {
        openConfirmModal();
      }
    });
  }

  if (confirmEditBtn) {
    confirmEditBtn.addEventListener('click', closeConfirmModal);
  }

  document.querySelectorAll('[data-modal-close]').forEach(function (element) {
    element.addEventListener('click', function () {
      if (confirmModal && confirmModal.classList.contains('is-open')) {
        closeConfirmModal();
      }
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && confirmModal && confirmModal.classList.contains('is-open')) {
      closeConfirmModal();
    }
  });

  function buildItemsPayload() {
    return cartItems.map(function (item) {
      return { service_id: item.service_id, quantity: item.quantity };
    });
  }

  if (confirmProceedBtn) {
    confirmProceedBtn.addEventListener('click', function () {
      itemsJsonField.value = JSON.stringify(buildItemsPayload());
      confirmProceedBtn.disabled = true;
      confirmProceedBtn.textContent = 'Placing order...';
      newOrderForm.submit();
    });
  }

  // Belt-and-suspenders: never allow the raw <form> to submit without
  // the confirmation step (e.g. pressing Enter in a text field).
  if (newOrderForm) {
    newOrderForm.addEventListener('submit', function (event) {
      if (itemsJsonField.value === '[]' || itemsJsonField.value === '') {
        event.preventDefault();
      }
    });
  }
})();
