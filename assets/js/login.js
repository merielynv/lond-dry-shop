/**
 * LOND Dry Shop — Log In Page
 * Client-side validation (Requirement #6) + small UX niceties.
 * NOTE: This is a first line of defense only. The real validation and
 * security checks happen server-side in auth/login_process.php.
 */
(function () {
  'use strict';

  const form = document.getElementById('loginForm');
  const usernameInput = document.getElementById('username');
  const passwordInput = document.getElementById('password');
  const usernameError = document.getElementById('usernameError');
  const passwordError = document.getElementById('passwordError');
  const loginBtn = document.getElementById('loginBtn');
  const togglePasswordBtn = document.getElementById('togglePassword');

  function setFieldError(input, errorEl, message) {
    const field = input.closest('.field');
    if (message) {
      field.classList.add('has-error');
      errorEl.textContent = message;
    } else {
      field.classList.remove('has-error');
      errorEl.textContent = '';
    }
  }

  function validateUsername() {
    const value = usernameInput.value.trim();
    if (value === '') {
      setFieldError(usernameInput, usernameError, 'Please enter your username.');
      return false;
    }
    if (value.length < 3) {
      setFieldError(usernameInput, usernameError, 'Username looks too short.');
      return false;
    }
    setFieldError(usernameInput, usernameError, '');
    return true;
  }

  function validatePassword() {
    const value = passwordInput.value;
    if (value === '') {
      setFieldError(passwordInput, passwordError, 'Please enter your password.');
      return false;
    }
    setFieldError(passwordInput, passwordError, '');
    return true;
  }

  // Validate as the user leaves a field, not on every keystroke (less noisy).
  usernameInput.addEventListener('blur', validateUsername);
  passwordInput.addEventListener('blur', validatePassword);

  // Clear the error the moment they start fixing it.
  usernameInput.addEventListener('input', () => setFieldError(usernameInput, usernameError, ''));
  passwordInput.addEventListener('input', () => setFieldError(passwordInput, passwordError, ''));

  // Show/hide password.
  togglePasswordBtn.addEventListener('click', () => {
    const showing = passwordInput.type === 'text';
    passwordInput.type = showing ? 'password' : 'text';
    togglePasswordBtn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    togglePasswordBtn.classList.toggle('is-active', !showing);
  });

  form.addEventListener('submit', (event) => {
    const usernameOk = validateUsername();
    const passwordOk = validatePassword();

    if (!usernameOk || !passwordOk) {
      event.preventDefault();
      (usernameOk ? passwordInput : usernameInput).focus();
      return;
    }

    // Prevent double-submits and give feedback while the server checks credentials.
    loginBtn.disabled = true;
    loginBtn.classList.add('is-loading');
  });

  // If the page was reloaded after a failed login (browser back/forward cache),
  // make sure the button isn't stuck in its loading state.
  window.addEventListener('pageshow', () => {
    loginBtn.disabled = false;
    loginBtn.classList.remove('is-loading');
  });
})();
