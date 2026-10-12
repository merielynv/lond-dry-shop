/**
 * LOND Dry Shop — New Order & Customer Management Interface
 *
 * Handles:
 *  - AJAX customer lookup + selection (Requirement #5)
 *  - Registering a NEW customer and EDITING an existing one through a
 *    structured form: last name, first name, optional middle initial
 *    and suffix; province, city/municipality, barangay, optional street
 *  - Building a multi-line service cart (Requirement #4)
 *  - Client-side validation before review (Requirement #6) - the server
 *    (customer_action.php / new_order_action.php) re-checks everything
 *  - The "double-check" confirmation modal
 *
 * Prices shown here are for staff convenience only; the server
 * re-reads prices from the database when saving the order.
 */
(function () {
  'use strict';

  /* =========================================================
     LOCATION SUGGESTIONS (edit these lists to add more places)
     Inputs stay free-text, so any place can still be typed.
     ========================================================= */

  var DEFAULT_PROVINCE = 'Laguna';
  var DEFAULT_CITY = 'City of Cabuyao';

  var LOCATIONS = {
    'Laguna': {
      cities: [
        'City of Biñan', 'City of Cabuyao', 'City of Calamba', 'City of San Pablo',
        'City of San Pedro', 'City of Santa Rosa',
        'Alaminos', 'Bay', 'Calauan', 'Cavinti', 'Famy', 'Kalayaan', 'Liliw', 'Los Baños',
        'Luisiana', 'Lumban', 'Mabitac', 'Magdalena', 'Majayjay', 'Nagcarlan', 'Paete',
        'Pagsanjan', 'Pakil', 'Pangil', 'Pila', 'Rizal', 'Santa Cruz', 'Santa Maria',
        'Siniloan', 'Victoria'
      ],
      barangays: {
        'City of Cabuyao': [
          'Baclaran', 'Banay-banay', 'Banlic', 'Bigaa', 'Butong', 'Casile', 'Diezmo',
          'Gulod', 'Mamatid', 'Marinig', 'Niugan', 'Pittland', 'Pulo', 'Sala',
          'San Isidro', 'Poblacion Uno', 'Poblacion Dos', 'Poblacion Tres'
        ]
      }
    }
  };
  var EXTRA_PROVINCES = ['Batangas', 'Cavite', 'Quezon', 'Rizal', 'Metro Manila'];

  /* =========================================================
     STATE + ELEMENT REFERENCES
     ========================================================= */

  var cartItems = [];        // { service_id, name, unit_type, quantity, unit_price, subtotal }
  var selectedCustomer = null; // full customer row from the server

  function $(id) { return document.getElementById(id); }

  var customerSearch = $('customerSearch');
  var lookupResults = $('lookupResults');
  var customerIdField = $('customerId');
  var customerEmpty = $('customerEmpty');
  var customerSelected = $('customerSelected');
  var selectedAvatar = $('selectedAvatar');
  var selectedName = $('selectedName');
  var selectedPhone = $('selectedPhone');
  var selectedAddress = $('selectedAddress');
  var customerSavedHint = $('customerSavedHint');
  var newCustomerBtn = $('newCustomerBtn');
  var editCustomerBtn = $('editCustomerBtn');
  var changeCustomerBtn = $('changeCustomerBtn');

  var customerModal = $('customerModal');
  var customerForm = $('customerForm');
  var customerFormAction = $('customerFormAction');
  var customerFormId = $('customerFormId');
  var customerModalEyebrow = $('customerModalEyebrow');
  var customerModalTitle = $('customerModalTitle');
  var customerModalDesc = $('customerModalDesc');
  var customerEditNote = $('customerEditNote');
  var customerFormError = $('customerFormError');
  var customerSubmitBtn = $('customerSubmitBtn');
  var useExistingBtn = $('useExistingBtn');
  var namePreview = $('namePreview');
  var addressPreview = $('addressPreview');

  var cLastName = $('cLastName');
  var cFirstName = $('cFirstName');
  var cMiddle = $('cMiddle');
  var cSuffix = $('cSuffix');
  var cPhone = $('cPhone');
  var cProvince = $('cProvince');
  var cCity = $('cCity');
  var cBarangay = $('cBarangay');
  var cStreet = $('cStreet');

  var serviceSelect = $('serviceSelect');
  var quantityInput = $('quantityInput');
  var quantityLabel = $('quantityLabel');
  var addItemBtn = $('addItemBtn');
  var cartBody = $('cartBody');

  var paymentMethodSelect = $('paymentMethod');
  var referenceField = $('referenceField');
  var referenceNumberInput = $('referenceNumber');
  var amountPaidInput = $('amountPaid');
  var paidError = $('paidError');
  var summaryTotal = $('summaryTotal');
  var summaryChange = $('summaryChange');

  var reviewOrderBtn = $('reviewOrderBtn');
  var newOrderForm = $('newOrderForm');
  var itemsJsonField = $('itemsJson');

  var confirmModal = $('confirmModal');
  var confirmCustomerName = $('confirmCustomerName');
  var confirmCustomerPhone = $('confirmCustomerPhone');
  var confirmItemsList = $('confirmItemsList');
  var confirmTotal = $('confirmTotal');
  var confirmPaymentMethod = $('confirmPaymentMethod');
  var confirmAmountPaid = $('confirmAmountPaid');
  var confirmChange = $('confirmChange');
  var confirmEditBtn = $('confirmEditBtn');
  var confirmProceedBtn = $('confirmProceedBtn');

  function peso(amount) {
    var value = isNaN(amount) ? 0 : amount;
    return '₱' + value.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
  }

  function round2(value) {
    return Math.round(value * 100) / 100;
  }

  /* =========================================================
     GENERIC MODAL HELPERS
     ========================================================= */

  function openModal(modal) {
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeModal(modal) {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.staff-modal.is-open')) {
      document.body.style.overflow = '';
    }
  }

  document.querySelectorAll('[data-modal-close]').forEach(function (element) {
    element.addEventListener('click', function () {
      var modal = element.closest('.staff-modal');
      if (modal) closeModal(modal);
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      document.querySelectorAll('.staff-modal.is-open').forEach(closeModal);
    }
  });

  /* =========================================================
     CUSTOMER NAME / ADDRESS COMPOSING
     (mirrors composeFullName / composeAddress in customer_action.php)
     ========================================================= */

  function cleanInitial(value) {
    return value.replace(/[^A-Za-zÀ-ÿ]/g, '').toUpperCase();
  }

  function composeFullName(first, middle, last, suffix) {
    var parts = [first];
    if (middle) parts.push(middle + '.');
    parts.push(last);
    if (suffix) parts.push(suffix);
    return parts.filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
  }

  function composeAddress(street, barangay, city, province) {
    var parts = [];
    if (street) parts.push(street);
    if (barangay) parts.push(/^(brgy|barangay)\b/i.test(barangay) ? barangay : 'Brgy. ' + barangay);
    if (city) parts.push(city);
    if (province) parts.push(province);
    return parts.join(', ');
  }

  function updatePreviews() {
    var name = composeFullName(
      cFirstName.value.trim(),
      cleanInitial(cMiddle.value),
      cLastName.value.trim(),
      cSuffix.value
    );
    var address = composeAddress(
      cStreet.value.trim(), cBarangay.value.trim(), cCity.value.trim(), cProvince.value.trim()
    );
    namePreview.textContent = name || '—';
    addressPreview.textContent = address || '—';
  }

  /* =========================================================
     LOCATION DATALISTS
     ========================================================= */

  function fillDatalist(listId, values) {
    var list = $(listId);
    list.innerHTML = '';
    values.forEach(function (value) {
      var option = document.createElement('option');
      option.value = value;
      list.appendChild(option);
    });
  }

  function refreshLocationLists() {
    var provinces = Object.keys(LOCATIONS).concat(EXTRA_PROVINCES);
    fillDatalist('provinceList', provinces);

    var province = LOCATIONS[cProvince.value.trim()];
    fillDatalist('cityList', province ? province.cities : []);

    var barangays = province && province.barangays[cCity.value.trim()];
    fillDatalist('barangayList', barangays || []);
  }

  [cProvince, cCity].forEach(function (input) {
    input.addEventListener('input', refreshLocationLists);
  });

  [cLastName, cFirstName, cMiddle, cSuffix, cStreet, cBarangay, cCity, cProvince].forEach(function (input) {
    input.addEventListener('input', updatePreviews);
    input.addEventListener('change', updatePreviews);
  });

  /* =========================================================
     SELECTED CUSTOMER CARD
     ========================================================= */

  function setSelectedCustomer(customer) {
    selectedCustomer = customer;
    customerIdField.value = customer.customer_id;

    selectedAvatar.textContent = (customer.first_name || customer.full_name || '?').charAt(0).toUpperCase();
    selectedName.textContent = customer.full_name;
    selectedPhone.textContent = customer.phone_number;
    selectedAddress.textContent = customer.address || 'No address on file';

    customerEmpty.hidden = true;
    customerSelected.hidden = false;
  }

  function clearSelectedCustomer() {
    selectedCustomer = null;
    customerIdField.value = '';
    customerSelected.hidden = true;
    customerEmpty.hidden = false;
    customerSavedHint.hidden = true;
  }

  function flashSavedHint(message) {
    customerSavedHint.textContent = '✓ ' + message;
    customerSavedHint.hidden = false;
  }

  if (changeCustomerBtn) {
    changeCustomerBtn.addEventListener('click', function () {
      clearSelectedCustomer();
      customerSearch.focus();
    });
  }

  /* =========================================================
     CUSTOMER LOOKUP (AJAX) - suggestion list for same-name matches
     ========================================================= */

  var lookupTimer = null;

  function runLookup(term) {
    fetch('customer_lookup.php?term=' + encodeURIComponent(term), { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (data) { renderLookupResults(data.customers || []); })
      .catch(function () { renderLookupResults([]); });
  }

  function renderLookupResults(customers) {
    lookupResults.innerHTML = '';

    if (customers.length === 0) {
      lookupResults.innerHTML = '<p class="lookup-empty">No matching customer. Use “＋ New Customer” to register them.</p>';
      lookupResults.hidden = false;
      return;
    }

    customers.forEach(function (customer) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'lookup-result';
      button.innerHTML = '<strong></strong><span></span>';
      button.querySelector('strong').textContent = customer.full_name;
      button.querySelector('span').textContent =
        customer.phone_number + (customer.address ? ' · ' + customer.address : '');

      button.addEventListener('click', function () {
        setSelectedCustomer(customer);
        lookupResults.hidden = true;
        customerSearch.value = '';
        customerSavedHint.hidden = true;
      });

      lookupResults.appendChild(button);
    });

    lookupResults.hidden = false;
  }

  if (customerSearch) {
    customerSearch.addEventListener('input', function () {
      var term = customerSearch.value.trim();
      clearTimeout(lookupTimer);

      if (term.length < 2) {
        lookupResults.hidden = true;
        return;
      }
      lookupTimer = setTimeout(function () { runLookup(term); }, 250);
    });

    document.addEventListener('click', function (event) {
      if (!lookupResults.contains(event.target) && event.target !== customerSearch) {
        lookupResults.hidden = true;
      }
    });
  }

  /* =========================================================
     CUSTOMER MODAL (add / edit)
     ========================================================= */

  var duplicateCustomer = null; // set when the server reports an existing phone number

  function clearFormErrors() {
    customerForm.querySelectorAll('[data-error-for]').forEach(function (el) {
      el.textContent = '';
      el.classList.remove('is-error');
    });
    customerForm.querySelectorAll('.has-error').forEach(function (el) {
      el.classList.remove('has-error');
    });
    customerFormError.hidden = true;
    customerFormError.textContent = '';
    useExistingBtn.hidden = true;
    duplicateCustomer = null;
  }

  function showFieldErrors(errors) {
    Object.keys(errors).forEach(function (field) {
      var hint = customerForm.querySelector('[data-error-for="' + field + '"]');
      var input = customerForm.querySelector('[name="' + field + '"]');
      if (hint) {
        hint.textContent = errors[field];
        hint.classList.add('is-error');
      }
      if (input) input.classList.add('has-error');
    });
  }

  function openCustomerModal(mode, customer) {
    clearFormErrors();
    customerForm.reset();

    if (mode === 'edit' && customer) {
      customerFormAction.value = 'update';
      customerFormId.value = customer.customer_id;
      customerModalEyebrow.textContent = 'Edit customer';
      customerModalTitle.textContent = 'Edit Customer Details';
      customerModalDesc.textContent = 'Update a new phone number, a new address, or fix a spelling mistake.';
      customerEditNote.hidden = false;
      customerSubmitBtn.textContent = 'Save Changes';

      cLastName.value = customer.last_name || '';
      cFirstName.value = customer.first_name || '';
      cMiddle.value = customer.middle_initial || '';
      cSuffix.value = customer.suffix || '';
      cPhone.value = customer.phone_number || '';
      cProvince.value = customer.province || '';
      cCity.value = customer.city || '';
      cBarangay.value = customer.barangay || '';
      cStreet.value = customer.street_address || '';
    } else {
      customerFormAction.value = 'create';
      customerFormId.value = '';
      customerModalEyebrow.textContent = 'New customer';
      customerModalTitle.textContent = 'Register New Customer';
      customerModalDesc.textContent = 'Fields marked * are required.';
      customerEditNote.hidden = true;
      customerSubmitBtn.textContent = 'Save Customer';

      cProvince.value = DEFAULT_PROVINCE;
      cCity.value = DEFAULT_CITY;
    }

    refreshLocationLists();
    updatePreviews();
    openModal(customerModal);
    setTimeout(function () { cLastName.focus(); }, 50);
  }

  if (newCustomerBtn) {
    newCustomerBtn.addEventListener('click', function () {
      lookupResults.hidden = true;
      openCustomerModal('create', null);
    });
  }

  // Always pre-fill the Edit form from the customer's CURRENT saved record
  // (not just what the search list returned), so staff never have to ask
  // the customer for details that are already on file.
  function loadCustomerRecord(customerId) {
    return fetch('customer_action.php?action=get&customer_id=' + encodeURIComponent(customerId), {
      credentials: 'same-origin'
    })
      .then(function (response) { return response.json(); })
      .then(function (data) { return data.ok ? data.customer : null; })
      .catch(function () { return null; });
  }

  if (editCustomerBtn) {
    editCustomerBtn.addEventListener('click', function () {
      if (!selectedCustomer) return;

      editCustomerBtn.disabled = true;
      loadCustomerRecord(selectedCustomer.customer_id).then(function (fresh) {
        if (fresh) setSelectedCustomer(fresh); // keep the card in sync too
        openCustomerModal('edit', fresh || selectedCustomer);
        editCustomerBtn.disabled = false;
      });
    });
  }

  if (useExistingBtn) {
    useExistingBtn.addEventListener('click', function () {
      if (duplicateCustomer) {
        setSelectedCustomer(duplicateCustomer);
        closeModal(customerModal);
      }
    });
  }

  // Client-side checks (the server re-validates every rule).
  function validateCustomerForm() {
    var errors = {};
    var namePattern = /^[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\s.'’\-]*$/;

    if (!cLastName.value.trim()) errors.last_name = 'Last name is required.';
    else if (!namePattern.test(cLastName.value.trim())) errors.last_name = 'Use letters only.';

    if (!cFirstName.value.trim()) errors.first_name = 'First name is required.';
    else if (!namePattern.test(cFirstName.value.trim())) errors.first_name = 'Use letters only.';

    var mi = cleanInitial(cMiddle.value);
    if (cMiddle.value.trim() && (mi.length < 1 || mi.length > 2)) {
      errors.middle_initial = 'Middle initial should be 1-2 letters (e.g. D).';
    }

    var phone = cPhone.value.replace(/[\s\-()]/g, '');
    if (/^\+639\d{9}$/.test(phone)) phone = '0' + phone.slice(3);
    if (!cPhone.value.trim()) errors.phone_number = 'Phone number is required.';
    else if (!/^09\d{9}$/.test(phone)) errors.phone_number = 'Enter a valid mobile number, e.g. 09171234567.';

    if (!cProvince.value.trim()) errors.province = 'Province is required.';
    if (!cCity.value.trim()) errors.city = 'City / Municipality is required.';
    if (!cBarangay.value.trim()) errors.barangay = 'Barangay is required.';

    return errors;
  }

  if (customerForm) {
    customerForm.addEventListener('submit', function (event) {
      event.preventDefault();
      clearFormErrors();

      var errors = validateCustomerForm();
      if (Object.keys(errors).length > 0) {
        showFieldErrors(errors);
        return;
      }

      var isEdit = customerFormAction.value === 'update';
      customerSubmitBtn.disabled = true;
      customerSubmitBtn.textContent = 'Saving...';

      fetch('customer_action.php', {
        method: 'POST',
        body: new FormData(customerForm),
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().then(function (data) { return data; });
        })
        .then(function (data) {
          if (data.ok) {
            setSelectedCustomer(data.customer);
            flashSavedHint(data.message);
            closeModal(customerModal);
            return;
          }

          if (data.errors) showFieldErrors(data.errors);
          if (data.existing) {
            duplicateCustomer = data.existing;
            useExistingBtn.hidden = false;
          }
          if (!data.errors) {
            customerFormError.textContent = data.message || 'Unable to save the customer. Please try again.';
            customerFormError.hidden = false;
          }
        })
        .catch(function () {
          customerFormError.textContent =
            'Unable to save the customer. Your session may have expired — please refresh the page and try again.';
          customerFormError.hidden = false;
        })
        .then(function () {
          customerSubmitBtn.disabled = false;
          customerSubmitBtn.textContent = isEdit ? 'Save Changes' : 'Save Customer';
        });
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
    var existing = cartItems.find(function (item) { return item.service_id === serviceId; });

    if (existing) {
      existing.quantity += quantity;
      existing.subtotal = round2(existing.quantity * existing.unit_price);
    } else {
      cartItems.push({
        service_id: serviceId,
        name: option.getAttribute('data-name'),
        unit_type: option.getAttribute('data-unit'),
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
    referenceField.hidden = (method !== 'Online');
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

    if (!selectedCustomer) {
      errors.push('Please select an existing customer or register a new one.');
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
    if (method === 'Online' && referenceNumberInput.value.trim() === '') {
      errors.push('A reference number is required for Online payments.');
    }

    if (errors.length > 0) {
      alert(errors.join('\n'));
      return false;
    }
    return true;
  }

  function openConfirmModal() {
    confirmCustomerName.textContent = selectedCustomer.full_name;
    confirmCustomerPhone.textContent = selectedCustomer.phone_number;

    confirmItemsList.innerHTML = '';
    cartItems.forEach(function (item) {
      var li = document.createElement('li');
      li.innerHTML = '<span></span><strong></strong>';
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

    openModal(confirmModal);
  }

  if (reviewOrderBtn) {
    reviewOrderBtn.addEventListener('click', function () {
      if (validateBeforeReview()) {
        openConfirmModal();
      }
    });
  }

  if (confirmEditBtn) {
    confirmEditBtn.addEventListener('click', function () { closeModal(confirmModal); });
  }

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

  // Never allow the raw <form> to submit without the confirmation step
  // (e.g. pressing Enter in a text field).
  if (newOrderForm) {
    newOrderForm.addEventListener('submit', function (event) {
      if (itemsJsonField.value === '[]' || itemsJsonField.value === '') {
        event.preventDefault();
      }
    });
  }
})();