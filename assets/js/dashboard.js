/**
 * LOND Dry Shop — Admin Dashboard
 * Small UI behaviors only: mobile nav toggle + profile dropdown.
 * No page data is fetched here — all dashboard figures are rendered
 * server-side in dashboard.php.
 */
(function () {
  'use strict';

  const navToggle = document.getElementById('navToggle');
  const adminNav = document.getElementById('adminNav');
  const profileTrigger = document.getElementById('profileTrigger');
  const profileMenu = document.getElementById('profileMenu');

  // Mobile nav toggle
  if (navToggle && adminNav) {
    navToggle.addEventListener('click', () => {
      const isOpen = adminNav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  // Profile dropdown
  if (profileTrigger && profileMenu) {
    profileTrigger.addEventListener('click', (event) => {
      event.stopPropagation();
      const isOpen = profileMenu.classList.toggle('is-open');
      profileTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
      if (!profileMenu.contains(event.target) && !profileTrigger.contains(event.target)) {
        profileMenu.classList.remove('is-open');
        profileTrigger.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        profileMenu.classList.remove('is-open');
        profileTrigger.setAttribute('aria-expanded', 'false');
      }
    });
  }
})();