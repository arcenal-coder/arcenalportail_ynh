// Progressive enhancement only; the server still validates profile, session and CSRF.
document.querySelectorAll('[data-profile-switch]').forEach(form => {
  const select = form.querySelector('select');
  if (!select || typeof form.requestSubmit !== 'function') return;
  form.classList.add('enhanced');
  select.addEventListener('change', () => form.requestSubmit());
});
