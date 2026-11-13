'use strict';

/* ---------- Tema claro / escuro ----------
   Carregado no <head>, sem "defer", para aplicar o tema antes da página aparecer
   (evita o "piscar" de tema errado). A escolha da pessoa fica salva neste navegador.

   Tema inicial quando a pessoa ainda não escolheu:
     'dark'   -> escuro (visual original do site)
     'light'  -> claro
     'system' -> segue o tema do celular/computador */
(() => {
  const DEFAULT_THEME = 'dark';
  const KEY = 'azimute-tema';
  const BAR_COLOR = { dark: '#111111', light: '#ffffff' };
  const root = document.documentElement;

  const saved = () => {
    try {
      const value = localStorage.getItem(KEY);
      return value === 'dark' || value === 'light' ? value : null;
    } catch {
      return null;
    }
  };

  const system = () => (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
  const initial = () => saved() || (DEFAULT_THEME === 'system' ? system() : DEFAULT_THEME);

  const labels = (theme) => (theme === 'dark'
    ? { text: 'Ativar tema claro', icon: 'fa-sun' }
    : { text: 'Ativar tema escuro', icon: 'fa-moon' });

  const paintButtons = (theme) => {
    const { text, icon } = labels(theme);
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
      btn.setAttribute('aria-label', text);
      btn.title = text;
      const i = btn.querySelector('i');
      if (i) i.className = `fa-solid ${icon}`;
    });
  };

  const apply = (theme) => {
    root.dataset.theme = theme;
    const bar = document.querySelector('meta[name="theme-color"]');
    if (bar) bar.content = BAR_COLOR[theme];
    paintButtons(theme);
  };

  apply(initial());

  document.addEventListener('DOMContentLoaded', () => {
    paintButtons(root.dataset.theme);

    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem(KEY, next); } catch { /* navegação privada: vale só nesta visita */ }
        apply(next);
      });
    });
  });

  // Sem escolha salva e seguindo o sistema: acompanha mudanças do aparelho
  if (DEFAULT_THEME === 'system') {
    matchMedia('(prefers-color-scheme: light)').addEventListener('change', () => {
      if (!saved()) apply(system());
    });
  }
})();
