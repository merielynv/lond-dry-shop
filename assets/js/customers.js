/**
 * LOND Dry Shop — Admin Customers page
 *
 * - Live search over the customer table
 * - Add / Edit customer modal (same structured form as the staff New Order
 *   modal: last name, first name, optional middle initial + suffix;
 *   province, city/municipality, barangay, optional street)
 *   Saves through admin/customer_action.php (AJAX). The server re-checks
 *   every rule — the checks here are only a first line of defense.
 * - View customer modal
 * - Delete customer modal
 */
(function () {
    'use strict';

    function $(id) { return document.getElementById(id); }

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
       CUSTOMER SEARCH
       ========================================================= */

    var search = $('customerSearch');
    var rows = document.querySelectorAll('.customer-row');
    var noResults = $('customersNoResults');

    function filterCustomers() {
        var searchText = search.value.trim().toLowerCase();
        var visibleCount = 0;

        rows.forEach(function (row) {
            var searchData = row.dataset.search || '';

            if (searchText === '' || searchData.indexOf(searchText) !== -1) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (noResults) {
            noResults.style.display =
                (visibleCount === 0 && rows.length > 0) ? 'block' : 'none';
        }
    }

    if (search) {
        search.addEventListener('input', filterCustomers);
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

        if (!document.querySelector('.customer-modal.is-open')) {
            document.body.style.overflow = '';
        }
    }

    /* =========================================================
       ADD / EDIT CUSTOMER MODAL
       ========================================================= */

    var customerModal = $('customerModal');
    var customerForm = $('customerForm');
    var customerFormAction = $('customerFormAction');
    var customerId = $('customerId');
    var customerModalEyebrow = $('customerModalEyebrow');
    var customerModalTitle = $('customerModalTitle');
    var customerModalDescription = $('customerModalDescription');
    var customerEditNote = $('customerEditNote');
    var customerFormError = $('customerFormError');
    var customerSubmit = $('customerSubmit');

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

    /* ---------- Name / address preview (mirrors customer_action.php) ---------- */

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

    /* ---------- Location datalists ---------- */

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
        fillDatalist('provinceList', Object.keys(LOCATIONS).concat(EXTRA_PROVINCES));

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

    /* ---------- Error display ---------- */

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

    /* ---------- Open (add or edit) ---------- */

    function openCustomerModal(editData) {
        clearFormErrors();
        customerForm.reset();

        if (editData) {
            customerFormAction.value = 'update';
            customerId.value = editData.id;
            customerModalEyebrow.textContent = 'Edit customer';
            customerModalTitle.textContent = 'Edit Customer Details';
            customerModalDescription.textContent =
                'Update a phone number, an address, or fix a spelling mistake.';
            customerEditNote.hidden = false;
            customerSubmit.textContent = 'Save Changes';

            cLastName.value = editData.lastName || '';
            cFirstName.value = editData.firstName || '';
            cMiddle.value = editData.middle || '';
            cSuffix.value = editData.suffix || '';
            cPhone.value = editData.phone || '';
            cProvince.value = editData.province || '';
            cCity.value = editData.city || '';
            cBarangay.value = editData.barangay || '';
            cStreet.value = editData.street || '';
        } else {
            customerFormAction.value = 'create';
            customerId.value = '';
            customerModalEyebrow.textContent = 'New customer';
            customerModalTitle.textContent = 'Register New Customer';
            customerModalDescription.textContent = 'Fields marked * are required.';
            customerEditNote.hidden = true;
            customerSubmit.textContent = 'Save Customer';

            cProvince.value = DEFAULT_PROVINCE;
            cCity.value = DEFAULT_CITY;
        }

        customerSubmit.disabled = false;

        refreshLocationLists();
        updatePreviews();
        openModal(customerModal);

        setTimeout(function () { cLastName.focus(); }, 50);
    }

    var openCustomerButton = $('openCustomerModal');

    if (openCustomerButton) {
        openCustomerButton.addEventListener('click', function () {
            openCustomerModal(null);
        });
    }

    document.querySelectorAll('.customer-edit').forEach(function (button) {
        button.addEventListener('click', function () {
            openCustomerModal({
                id: button.dataset.id,
                lastName: button.dataset.lastName,
                firstName: button.dataset.firstName,
                middle: button.dataset.middle,
                suffix: button.dataset.suffix,
                phone: button.dataset.phone,
                province: button.dataset.province,
                city: button.dataset.city,
                barangay: button.dataset.barangay,
                street: button.dataset.street
            });
        });
    });

    document.querySelectorAll('.customer-modal-close').forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(customerModal);
        });
    });

    /* ---------- Client-side checks (the server re-validates everything) ---------- */

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

    /* ---------- Submit (AJAX → admin/customer_action.php) ---------- */

    function resetSubmitButton() {
        customerSubmit.disabled = false;
        customerSubmit.textContent =
            customerFormAction.value === 'update' ? 'Save Changes' : 'Save Customer';
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

            customerSubmit.disabled = true;
            customerSubmit.textContent = 'Saving...';

            fetch('customer_action.php', {
                method: 'POST',
                body: new FormData(customerForm),
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.ok) {
                        // Reload so the table, stats and success banner are all fresh.
                        window.location.href = 'customers.php?success=' + (isEdit ? 'updated' : 'added');
                        return;
                    }

                    if (data.errors) showFieldErrors(data.errors);

                    if (!data.errors) {
                        customerFormError.textContent =
                            data.message || 'Unable to save the customer. Please try again.';
                        customerFormError.hidden = false;
                    }

                    resetSubmitButton();
                })
                .catch(function () {
                    customerFormError.textContent =
                        'Network problem — please check your connection and try again.';
                    customerFormError.hidden = false;
                    resetSubmitButton();
                });
        });
    }

    /* =========================================================
       VIEW CUSTOMER MODAL
       ========================================================= */

    var viewCustomerModal = $('viewCustomerModal');
    var viewCustomerName = $('viewCustomerName');
    var viewCustomerId = $('viewCustomerId');
    var viewCustomerAvatar = $('viewCustomerAvatar');
    var viewCustomerPhone = $('viewCustomerPhone');
    var viewCustomerAddress = $('viewCustomerAddress');
    var viewCustomerOrders = $('viewCustomerOrders');
    var viewCustomerValue = $('viewCustomerValue');
    var viewCustomerLastOrder = $('viewCustomerLastOrder');
    var viewCustomerCreated = $('viewCustomerCreated');

    function openViewCustomer(button) {
        var name = button.dataset.name || '';

        viewCustomerName.textContent = name;
        viewCustomerId.textContent = 'Customer #' + button.dataset.id;
        viewCustomerAvatar.textContent = name.charAt(0).toUpperCase();
        viewCustomerPhone.textContent = button.dataset.phone;
        viewCustomerAddress.textContent = button.dataset.address || 'No address provided';
        viewCustomerOrders.textContent = button.dataset.orders;
        viewCustomerValue.textContent = '₱' + button.dataset.value;
        viewCustomerLastOrder.textContent = button.dataset.lastOrder;
        viewCustomerCreated.textContent = button.dataset.created;

        openModal(viewCustomerModal);
    }

    document.querySelectorAll('.customer-view').forEach(function (button) {
        button.addEventListener('click', function () {
            openViewCustomer(button);
        });
    });

    document.querySelectorAll('.customer-view-close').forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(viewCustomerModal);
        });
    });

    /* =========================================================
       DELETE CUSTOMER MODAL
       ========================================================= */

    var deleteModal = $('deleteCustomerModal');
    var deleteCustomerId = $('deleteCustomerId');
    var deleteCustomerName = $('deleteCustomerName');

    document.querySelectorAll('.customer-delete').forEach(function (button) {
        button.addEventListener('click', function () {
            deleteCustomerId.value = button.dataset.id;
            deleteCustomerName.textContent = button.dataset.name;
            openModal(deleteModal);
        });
    });

    document.querySelectorAll('.delete-modal-close').forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(deleteModal);
        });
    });

    /* =========================================================
       ESC KEY
       ========================================================= */

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('.customer-modal.is-open').forEach(closeModal);
    });

})();