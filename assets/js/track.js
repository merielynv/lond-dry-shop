/**
 * LOND Dry Shop — Public Order Tracking (claim-code entry)
 * Client-side validation + loading state. Server re-validates in status.php.
 */
(function () {
  'use strict';

  const form = document.getElementById('trackForm');
  const codeInput = document.getElementById('code');
  const codeError = document.getElementById('codeError');
  const trackBtn = document.getElementById('trackBtn');

  if (!form || !codeInput) return;

  function showError(message) {
    if (!codeError) return;
    if (message) {
      codeError.hidden = false;
      codeError.textContent = message;
      codeInput.classList.add('has-error');
    } else {
      codeError.hidden = true;
      codeError.textContent = '';
      codeInput.classList.remove('has-error');
    }
  }

  function validate() {
    const value = codeInput.value.trim();
    if (value === '') {
      showError('Please enter your claim code.');
      return false;
    }
    if (value.length > 30) {
      showError('That claim code looks too long.');
      return false;
    }
    if (!/^[A-Za-z0-9\-]+$/.test(value)) {
      showError('Use only letters, numbers, and hyphens.');
      return false;
    }
    showError('');
    return true;
  }

  codeInput.addEventListener('input', () => {
    // Normalize to uppercase as the user types (matches printed codes)
    const start = codeInput.selectionStart;
    const end = codeInput.selectionEnd;
    codeInput.value = codeInput.value.toUpperCase();
    codeInput.setSelectionRange(start, end);
    showError('');
  });

  codeInput.addEventListener('blur', validate);

  form.addEventListener('submit', (event) => {
    if (!validate()) {
      event.preventDefault();
      codeInput.focus();
      return;
    }
    trackBtn.disabled = true;
    trackBtn.classList.add('is-loading');
  });

  window.addEventListener('pageshow', () => {
    trackBtn.disabled = false;
    trackBtn.classList.remove('is-loading');
  });
})();
