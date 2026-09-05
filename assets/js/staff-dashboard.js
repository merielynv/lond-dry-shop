/**
 * LOND Dry Shop — Staff Shop Floor Tracker
 * Page-specific behavior: client-side search/filter over the order
 * board (Requirement #5) and the "Change status" confirmation modal.
 * Shared topbar behavior (nav toggle, profile dropdown/logout) lives
 * in staff-shell.js, loaded alongside this file.
 */
(function () {
  'use strict';

  /* =========================================================

  var search = document.getElementById('trackerSearch');
  var statusFilter = document.getElementById('trackerStatusFilter');
  var rows = document.querySelectorAll('.tracker-row');
  var noResults = document.getElementById('trackerNoResults');

  function filterTracker() {
    var searchText = (search ? search.value : '').trim().toLowerCase();
    var selectedStatus = statusFilter ? statusFilter.value : 'all';
    var visibleCount = 0;

    rows.forEach(function (row) {
      var searchData = row.dataset.search || '';
      var status = row.dataset.status || '';

      var matchesSearch = searchText === '' || searchData.indexOf(searchText) !== -1;
      var matchesStatus = selectedStatus === 'all' || status === selectedStatus;

      if (matchesSearch && matchesStatus) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });

    if (noResults) {
      noResults.style.display = (visibleCount === 0 && rows.length > 0) ? 'block' : 'none';
    }
  }

  if (search) {
    search.addEventListener('input', filterTracker);
  }
  if (statusFilter) {
    statusFilter.addEventListener('change', filterTracker);
  }

  /* =========================================================
     CHANGE STATUS MODAL
     ========================================================= */

  var statusModal = document.getElementById('statusModal');
  var statusOrderId = document.getElementById('statusOrderId');
  var newOrderStatus = document.getElementById('newOrderStatus');
  var statusClaimText = document.getElementById('statusClaimText');

  function openStatusModal(button) {
    if (!statusModal) return;

    statusOrderId.value = button.getAttribute('data-id');
    newOrderStatus.value = button.getAttribute('data-status');
    statusClaimText.textContent =
      'Update ' + button.getAttribute('data-claim') + ' to a new processing status.';

    statusModal.classList.add('is-open');
    statusModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeAllModals() {
    document.querySelectorAll('.staff-modal.is-open').forEach(function (modal) {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    });
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.change-status').forEach(function (button) {
    button.addEventListener('click', function () {
      openStatusModal(button);
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach(function (element) {
    element.addEventListener('click', closeAllModals);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeAllModals();
    }
  });

  // Prevent double submission of the status form.
  var statusForm = statusModal ? statusModal.querySelector('form') : null;
  if (statusForm) {
    statusForm.addEventListener('submit', function () {
      var submitButton = statusForm.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Saving...';
      }
    });
  }
})();
