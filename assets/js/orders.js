(function () {
    'use strict';

    /* =========================================================
       SEARCH AND FILTER
       ========================================================= */

    const search = document.getElementById('orderSearch');
    const statusFilter = document.getElementById('orderStatusFilter');

    const rows = document.querySelectorAll('.order-row');
    const noResults = document.getElementById('ordersNoResults');


    function filterOrders() {

        const searchText =
            search.value.trim().toLowerCase();

        const selectedStatus =
            statusFilter.value;

        let visibleCount = 0;


        rows.forEach(function (row) {

            const searchData =
                row.dataset.search;

            const status =
                row.dataset.status;


            const matchesSearch =
                searchText === '' ||
                searchData.indexOf(searchText) !== -1;


            const matchesStatus =
                selectedStatus === 'all' ||
                status === selectedStatus;


            if (matchesSearch && matchesStatus) {

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


    /* =========================================================
       SEARCH
       ========================================================= */

    if (search) {

        search.addEventListener('input', function () {

            filterOrders();

        });

    }


    /* =========================================================
       STATUS FILTER
       ========================================================= */

    if (statusFilter) {

        statusFilter.addEventListener('change', function () {

            filterOrders();

        });

    }


    /* =========================================================
       VIEW ORDER MODAL
       ========================================================= */

    const viewModal =
        document.getElementById('viewOrderModal');

    const viewClaimCode =
        document.getElementById('viewClaimCode');

    const viewCustomer =
        document.getElementById('viewCustomer');

    const viewPhone =
        document.getElementById('viewPhone');

    const viewStatus =
        document.getElementById('viewStatus');

    const viewAmount =
        document.getElementById('viewAmount');

    const viewStaff =
        document.getElementById('viewStaff');

    const viewDate =
        document.getElementById('viewDate');

    const viewNotes =
        document.getElementById('viewNotes');


    function openViewModal(button) {

        viewClaimCode.textContent =
            button.dataset.claim;

        viewCustomer.textContent =
            button.dataset.customer;

        viewPhone.textContent =
            button.dataset.phone;

        viewStatus.textContent =
            button.dataset.status;

        viewAmount.textContent =
            '₱' + button.dataset.amount;

        viewStaff.textContent =
            button.dataset.staff;

        viewDate.textContent =
            button.dataset.date;


        if (button.dataset.notes) {

            viewNotes.textContent =
                button.dataset.notes;

        } else {

            viewNotes.textContent =
                'No special instructions.';

        }


        viewModal.classList.add('is-open');

        viewModal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.style.overflow = 'hidden';
    }


    /* =========================================================
       STATUS MODAL
       ========================================================= */

    const statusModal =
        document.getElementById('statusModal');

    const statusOrderId =
        document.getElementById('statusOrderId');

    const newOrderStatus =
        document.getElementById('newOrderStatus');

    const statusClaimText =
        document.getElementById('statusClaimText');


    function openStatusModal(button) {

        statusOrderId.value =
            button.dataset.id;

        newOrderStatus.value =
            button.dataset.status;

        statusClaimText.textContent =
            'Update ' +
            button.dataset.claim +
            ' to a new processing status.';


        statusModal.classList.add('is-open');

        statusModal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.style.overflow = 'hidden';
    }


    /* =========================================================
       OPEN VIEW ORDER BUTTONS
       ========================================================= */

    document.querySelectorAll('.view-order').forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    openViewModal(button);

                }
            );

        }
    );


    /* =========================================================
       OPEN CHANGE STATUS BUTTONS
       ========================================================= */

    document.querySelectorAll('.change-status').forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    openStatusModal(button);

                }
            );

        }
    );


    /* =========================================================
       CLOSE MODALS
       ========================================================= */

    function closeModals() {

        viewModal.classList.remove('is-open');

        statusModal.classList.remove('is-open');


        viewModal.setAttribute(
            'aria-hidden',
            'true'
        );

        statusModal.setAttribute(
            'aria-hidden',
            'true'
        );


        document.body.style.overflow = '';

    }


    /* =========================================================
       CLOSE MODAL BUTTONS / BACKDROP
       ========================================================= */

    document.querySelectorAll('.modal-close-trigger').forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    closeModals();

                }
            );

        }
    );


    /* =========================================================
       CLOSE MODAL USING ESC KEY
       ========================================================= */

    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape' &&
                (
                    viewModal.classList.contains('is-open') ||
                    statusModal.classList.contains('is-open')
                )
            ) {

                closeModals();

            }

        }
    );


    /* =========================================================
       PREVENT DOUBLE SUBMISSION
       ========================================================= */

    const statusForms =
        statusModal.querySelectorAll('form');


    statusForms.forEach(function (form) {

        form.addEventListener(
            'submit',
            function () {

                const button =
                    form.querySelector(
                        'button[type="submit"]'
                    );


                if (button) {

                    button.disabled = true;

                    button.style.opacity = '0.7';

                    button.style.cursor = 'wait';

                }

            }
        );

    });

})();