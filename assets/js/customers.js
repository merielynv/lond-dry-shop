(function () {
    'use strict';

    /* =========================================================
       CUSTOMER SEARCH
       ========================================================= */

    const search =
        document.getElementById('customerSearch');

    const rows =
        document.querySelectorAll('.customer-row');

    const noResults =
        document.getElementById('customersNoResults');


    function filterCustomers() {

        const searchText =
            search.value.trim().toLowerCase();

        let visibleCount = 0;


        rows.forEach(function (row) {

            const searchData =
                row.dataset.search;


            if (
                searchText === '' ||
                searchData.indexOf(searchText) !== -1
            ) {

                row.style.display = '';

                visibleCount++;

            } else {

                row.style.display = 'none';

            }

        });


        if (noResults) {

            if (visibleCount === 0 && rows.length > 0) {

                noResults.style.display = 'block';

            } else {

                noResults.style.display = 'none';

            }

        }

    }


    if (search) {

        search.addEventListener('input', function () {

            filterCustomers();

        });

    }


    /* =========================================================
       ADD / EDIT CUSTOMER MODAL
       ========================================================= */

    const customerModal =
        document.getElementById('customerModal');

    const customerModalTitle =
        document.getElementById('customerModalTitle');

    const customerModalDescription =
        document.getElementById('customerModalDescription');

    const customerFormAction =
        document.getElementById('customerFormAction');

    const customerId =
        document.getElementById('customerId');

    const fullName =
        document.getElementById('fullName');

    const phoneNumber =
        document.getElementById('phoneNumber');

    const address =
        document.getElementById('address');

    const customerSubmit =
        document.getElementById('customerSubmit');


    function openCustomerModal(editData) {

        customerModal.classList.add('is-open');

        customerModal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.style.overflow = 'hidden';


        if (editData) {

            customerModalTitle.textContent =
                'Edit Customer';

            customerModalDescription.textContent =
                'Update the customer contact information.';

            customerFormAction.value =
                'edit';

            customerId.value =
                editData.id;

            fullName.value =
                editData.name;

            phoneNumber.value =
                editData.phone;

            address.value =
                editData.address;

            customerSubmit.textContent =
                'Save Changes';

        } else {

            customerModalTitle.textContent =
                'Add Customer';

            customerModalDescription.textContent =
                'Add contact information for a new customer.';

            customerFormAction.value =
                'add';

            customerId.value =
                '';

            fullName.value =
                '';

            phoneNumber.value =
                '';

            address.value =
                '';

            customerSubmit.textContent =
                'Add Customer';

        }


        setTimeout(function () {

            fullName.focus();

        }, 50);

    }


    function closeCustomerModal() {

        customerModal.classList.remove('is-open');

        customerModal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.style.overflow = '';

    }


    /* =========================================================
       ADD CUSTOMER
       ========================================================= */

    const openCustomerButton =
        document.getElementById('openCustomerModal');


    if (openCustomerButton) {

        openCustomerButton.addEventListener(
            'click',
            function () {

                openCustomerModal(null);

            }
        );

    }


    /* =========================================================
       EDIT CUSTOMER
       ========================================================= */

    document.querySelectorAll('.customer-edit').forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    openCustomerModal({

                        id: button.dataset.id,

                        name: button.dataset.name,

                        phone: button.dataset.phone,

                        address: button.dataset.address

                    });

                }
            );

        }
    );


    /* =========================================================
       CLOSE ADD / EDIT MODAL
       ========================================================= */

    document.querySelectorAll('.customer-modal-close').forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    closeCustomerModal();

                }
            );

        }
    );


    /* =========================================================
       VIEW CUSTOMER MODAL
       ========================================================= */

    const viewCustomerModal =
        document.getElementById('viewCustomerModal');

    const viewCustomerName =
        document.getElementById('viewCustomerName');

    const viewCustomerId =
        document.getElementById('viewCustomerId');

    const viewCustomerAvatar =
        document.getElementById('viewCustomerAvatar');

    const viewCustomerPhone =
        document.getElementById('viewCustomerPhone');

    const viewCustomerAddress =
        document.getElementById('viewCustomerAddress');

    const viewCustomerOrders =
        document.getElementById('viewCustomerOrders');

    const viewCustomerValue =
        document.getElementById('viewCustomerValue');

    const viewCustomerLastOrder =
        document.getElementById('viewCustomerLastOrder');

    const viewCustomerCreated =
        document.getElementById('viewCustomerCreated');


    function openViewCustomer(button) {

        const name =
            button.dataset.name;


        viewCustomerName.textContent =
            name;

        viewCustomerId.textContent =
            'Customer #' + button.dataset.id;

        viewCustomerAvatar.textContent =
            name.charAt(0).toUpperCase();

        viewCustomerPhone.textContent =
            button.dataset.phone;

        viewCustomerAddress.textContent =
            button.dataset.address ||
            'No address provided';

        viewCustomerOrders.textContent =
            button.dataset.orders;

        viewCustomerValue.textContent =
            '₱' + button.dataset.value;

        viewCustomerLastOrder.textContent =
            button.dataset.lastOrder;

        viewCustomerCreated.textContent =
            button.dataset.created;


        viewCustomerModal.classList.add('is-open');

        viewCustomerModal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.style.overflow = 'hidden';

    }


    document.querySelectorAll('.customer-view').forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    openViewCustomer(button);

                }
            );

        }
    );


    function closeViewCustomer() {

        viewCustomerModal.classList.remove('is-open');

        viewCustomerModal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.style.overflow = '';

    }


    document.querySelectorAll('.customer-view-close').forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    closeViewCustomer();

                }
            );

        }
    );


    /* =========================================================
       DELETE CUSTOMER MODAL
       ========================================================= */

    const deleteModal =
        document.getElementById('deleteCustomerModal');

    const deleteCustomerId =
        document.getElementById('deleteCustomerId');

    const deleteCustomerName =
        document.getElementById('deleteCustomerName');


    function openDeleteModal(button) {

        deleteCustomerId.value =
            button.dataset.id;

        deleteCustomerName.textContent =
            button.dataset.name;


        deleteModal.classList.add('is-open');

        deleteModal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.style.overflow = 'hidden';

    }


    document.querySelectorAll('.customer-delete').forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    openDeleteModal(button);

                }
            );

        }
    );


    function closeDeleteModal() {

        deleteModal.classList.remove('is-open');

        deleteModal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.style.overflow = '';

    }


    document.querySelectorAll('.delete-modal-close').forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    closeDeleteModal();

                }
            );

        }
    );


    /* =========================================================
       ESC KEY
       ========================================================= */

    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key !== 'Escape') {
                return;
            }


            if (customerModal.classList.contains('is-open')) {

                closeCustomerModal();

            }


            if (viewCustomerModal.classList.contains('is-open')) {

                closeViewCustomer();

            }


            if (deleteModal.classList.contains('is-open')) {

                closeDeleteModal();

            }

        }
    );


    /* =========================================================
       PREVENT DOUBLE SUBMIT
       ========================================================= */

    const customerForm =
        document.getElementById('customerForm');


    if (customerForm) {

        customerForm.addEventListener(
            'submit',
            function () {

                customerSubmit.disabled = true;

                customerSubmit.style.opacity = '0.7';

                customerSubmit.style.cursor = 'wait';

            }
        );

    }

})();