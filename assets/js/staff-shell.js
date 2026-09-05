/**
 * LOND Dry Shop — Staff Shell (shared topbar behavior)
 *
 * Mobile nav toggle + profile dropdown (which includes the Log out
 * link). Loaded on every staff page that uses the shared topbar
 * markup from staff.css (staff/dashboard.php, staff/new_order.php),
 * so "Log out" is always reachable no matter which page staff are on.
 */
(function () {
  'use strict';

  var navToggle = document.getElementById('navToggle');
  var staffNav = document.getElementById('staffNav');
  var profileTrigger = document.getElementById('profileTrigger');
  var profileMenu = document.getElementById('profileMenu');

  if (navToggle && staffNav) {
    navToggle.addEventListener('click', function () {
      var isOpen = staffNav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  if (profileTrigger && profileMenu) {
    profileTrigger.addEventListener('click', function (event) {
      event.stopPropagation();
      var isOpen = profileMenu.classList.toggle('is-open');
      profileTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', function (event) {
      if (!profileMenu.contains(event.target) && !profileTrigger.contains(event.target)) {
        profileMenu.classList.remove('is-open');
        profileTrigger.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        profileMenu.classList.remove('is-open');
        profileTrigger.setAttribute('aria-expanded', 'false');
      }
    });
  }
})();
