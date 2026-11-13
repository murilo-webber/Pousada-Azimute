'use strict';

/* ---------- Google Analytics 4 + eventos ----------
   1) Troque GA_ID abaixo pelo seu ID de métricas (GA4 > Administrador > Fluxos de dados;
      começa com "G-").
   2) Enquanto GA_ID for o valor de exemplo, este arquivo não faz nada: não carrega o
      Google e não mostra o aviso de cookies.
   3) Com o ID preenchido, o Analytics só carrega depois que a pessoa aceita o aviso
      de cookies (LGPD). */
(() => {
  const GA_ID = 'G-XXXXXXXXXX';
  const CHOICE_KEY = 'azimute-cookies';

  if (GA_ID === 'G-XXXXXXXXXX' || !/^G-[A-Z0-9]{6,}$/.test(GA_ID)) return;

  let active = false;
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }

  const send = (name, params) => { if (active) gtag('event', name, params); };

  const start = () => {
    if (active) return;
    active = true;
    gtag('js', new Date());
    gtag('config', GA_ID);
    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(GA_ID)}`;
    document.head.append(script);
  };

  /* ---------- Aviso de cookies ---------- */
  const choice = () => {
    try { return localStorage.getItem(CHOICE_KEY); } catch { return null; }
  };
  const remember = (value) => {
    try { localStorage.setItem(CHOICE_KEY, value); } catch { /* ignora */ }
  };

  const showBanner = () => {
    const box = document.createElement('div');
    box.className = 'consent';
    box.setAttribute('role', 'region');
    box.setAttribute('aria-label', 'Aviso de cookies');

    const text = document.createElement('p');
    text.textContent = 'Usamos cookies de estatística (Google Analytics) para entender como o site é usado e melhorá-lo. Você pode aceitar ou recusar.';

    const actions = document.createElement('div');
    actions.className = 'consent-actions';

    const accept = document.createElement('button');
    accept.type = 'button';
    accept.className = 'consent-accept';
    accept.textContent = 'Aceitar';
    accept.addEventListener('click', () => { remember('sim'); box.remove(); start(); });

    const decline = document.createElement('button');
    decline.type = 'button';
    decline.textContent = 'Recusar';
    decline.addEventListener('click', () => { remember('nao'); box.remove(); });

    actions.append(accept, decline);
    box.append(text, actions);
    document.body.append(box);
  };

  const stored = choice();
  if (stored === 'sim') start();
  else if (stored !== 'nao') document.addEventListener('DOMContentLoaded', showBanner);

  /* ---------- Eventos ---------- */
  const place = (el) => {
    if (el.closest('.whatsapp-float, .instagram-float')) return 'botao_flutuante';
    if (el.closest('.site-footer')) return 'rodape';
    if (el.closest('.home-hero')) return 'topo_da_home';
    if (el.closest('.cta-section')) return 'chamada_final';
    if (el.closest('.contact-section')) return 'pagina_contato';
    if (el.closest('.room-detail')) return 'pagina_do_quarto';
    return 'outro';
  };

  const roomOnPage = () => {
    if (!location.pathname.includes('quarto-')) return undefined;
    const title = document.querySelector('.page-hero h1');
    return title ? title.textContent.trim() : undefined;
  };

  document.addEventListener('click', (e) => {
    const link = e.target.closest('a[href]');

    if (link) {
      const href = link.getAttribute('href');

      if (link.hostname === 'wa.me') {
        send('whatsapp_click', { local: place(link), quarto: roomOnPage(), pagina: location.pathname });
      } else if (href.startsWith('tel:')) {
        send('phone_click', { local: place(link) });
      } else if (href.startsWith('mailto:')) {
        send('email_click', { local: place(link) });
      } else if (link.classList.contains('btn-map')) {
        send('map_directions_click', { local: place(link) });
      } else if (link.hostname === 'www.instagram.com') {
        send('instagram_click', { local: place(link) });
      } else if (link.classList.contains('btn-cta') && href.startsWith('quarto-')) {
        const card = link.closest('.room-card');
        const name = card ? card.querySelector('h3').textContent.trim() : undefined;
        send('room_details_click', { quarto: name });
      }
      return;
    }

    const spot = e.target.closest('[data-spot]');
    if (spot) {
      send('spot_photos_open', { atracao: spot.dataset.spot });
      return;
    }

    const photo = e.target.closest('.photo');
    if (photo) {
      const img = photo.querySelector('img');
      send('gallery_photo_open', { pagina: location.pathname, foto: img ? img.alt : undefined });
    }
  });
})();
