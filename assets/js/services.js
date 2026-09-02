(function () {
    'use strict';

    const modal = document.getElementById('serviceModal');

    const openAdd = document.getElementById('openAddModal');
    const openAddEmpty = document.getElementById('openAddModalEmpty');

    const closeModal = document.getElementById('closeModal');
    const cancelModal = document.getElementById('cancelModal');
    const backdrop = document.getElementById('modalBackdrop');

    const form = document.getElementById('serviceForm');

    const formAction = document.getElementById('formAction');
    const serviceId = document.getElementById('serviceId');

    const serviceName = document.getElementById('serviceName');
    const unitType = document.getElementById('unitType');
    const currentPrice = document.getElementById('currentPrice');
    const categoryType = document.getElementById('categoryType');

    const modalTitle = document.getElementById('modalTitle');
    const modalDescription = document.getElementById('modalDescription');
    const submitText = document.getElementById('submitText');

    const search = document.getElementById('serviceSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');

    const cards = document.querySelectorAll('.service-card');
    const noResults = document.getElementById('noResults');


    // =========================================================
    // OPEN MODAL
    // =========================================================

    function openModal(editData) {

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        document.body.style.overflow = 'hidden';


        // EDIT SERVICE
        if (editData) {

            modalTitle.textContent = 'Edit Service';

            modalDescription.textContent =
                'Update the service name, unit, category, or current price.';

            submitText.textContent = 'Save Changes';

            formAction.value = 'edit';

            serviceId.value = editData.id;

            serviceName.value = editData.name;

            unitType.value = editData.unit;

            currentPrice.value = editData.price;

            categoryType.value = editData.category;

        }

        // ADD SERVICE
        else {

            modalTitle.textContent = 'Add New Service';

            modalDescription.textContent =
                'Create a new service that can be selected when encoding an order.';

            submitText.textContent = 'Add Service';

            formAction.value = 'add';

            serviceId.value = '';

            form.reset();
        }


        // Put cursor in service name
        setTimeout(function () {

            serviceName.focus();

        }, 50);
    }


    // =========================================================
    // CLOSE MODAL
    // =========================================================

    function closeServiceModal() {

        modal.classList.remove('is-open');

        modal.setAttribute('aria-hidden', 'true');

        document.body.style.overflow = '';
    }


    // =========================================================
    // ADD SERVICE BUTTON
    // =========================================================

    if (openAdd) {

        openAdd.addEventListener('click', function () {

            openModal(null);

        });
    }


    // Add first service button
    if (openAddEmpty) {

        openAddEmpty.addEventListener('click', function () {

            openModal(null);

        });
    }


    // =========================================================
    // CLOSE BUTTONS
    // =========================================================

    if (closeModal) {

        closeModal.addEventListener('click', function () {

            closeServiceModal();

        });
    }


    if (cancelModal) {

        cancelModal.addEventListener('click', function () {

            closeServiceModal();

        });
    }


    // Click outside modal
    if (backdrop) {

        backdrop.addEventListener('click', function () {

            closeServiceModal();

        });
    }


    // =========================================================
    // ESC KEY
    // =========================================================

    document.addEventListener('keydown', function (event) {

        if (
            event.key === 'Escape' &&
            modal.classList.contains('is-open')
        ) {

            closeServiceModal();

        }

    });


    // =========================================================
    // EDIT SERVICE BUTTONS
    // =========================================================

    document.querySelectorAll('.edit-service').forEach(function (button) {

        button.addEventListener('click', function () {

            openModal({

                id: button.dataset.id,

                name: button.dataset.name,

                unit: button.dataset.unit,

                price: button.dataset.price,

                category: button.dataset.category

            });

        });

    });


    // =========================================================
    // FILTER SERVICES
    // =========================================================

    function filterServices() {

        const searchText =
            search.value.trim().toLowerCase();

        const selectedCategory =
            categoryFilter.value;

        const selectedStatus =
            statusFilter.value;


        let visibleCount = 0;


        cards.forEach(function (card) {

            const name =
                card.dataset.name;

            const category =
                card.dataset.category;

            const status =
                card.dataset.status;


            // Search
            const matchesSearch =
                searchText === '' ||
                name.indexOf(searchText) !== -1;


            // Category
            const matchesCategory =
                selectedCategory === 'all' ||
                category === selectedCategory;


            // Status
            const matchesStatus =
                selectedStatus === 'all' ||
                status === selectedStatus;


            // Show card
            if (
                matchesSearch &&
                matchesCategory &&
                matchesStatus
            ) {

                card.style.display = '';

                visibleCount++;

            }

            // Hide card
            else {

                card.style.display = 'none';

            }

        });


        // Show "No results"
        if (noResults) {

            if (visibleCount === 0) {

                noResults.style.display = 'block';

            }

            else {

                noResults.style.display = 'none';

            }

        }

    }


    // =========================================================
    // SEARCH
    // =========================================================

    if (search) {

        search.addEventListener('input', function () {

            filterServices();

        });

    }


    // =========================================================
    // CATEGORY FILTER
    // =========================================================

    if (categoryFilter) {

        categoryFilter.addEventListener('change', function () {

            filterServices();

        });

    }


    // =========================================================
    // STATUS FILTER
    // =========================================================

    if (statusFilter) {

        statusFilter.addEventListener('change', function () {

            filterServices();

        });

    }


    // =========================================================
    // FORM SUBMIT
    // =========================================================

    if (form) {

        form.addEventListener('submit', function () {

            const submitButton =
                form.querySelector('button[type="submit"]');


            if (submitButton) {

                submitButton.disabled = true;

                submitButton.style.opacity = '0.7';

                submitButton.style.cursor = 'wait';

            }

        });

    }

})();