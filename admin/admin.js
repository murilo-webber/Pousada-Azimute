'use strict';

/* Botão "mostrar senha" dos formulários do painel */
document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
  const input = document.getElementById(btn.dataset.togglePassword);
  if (!input) return;

  btn.addEventListener('click', () => {
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', String(show));
    btn.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
    btn.querySelector('i').className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
  });
});
