'use strict';

/* ---------- Imagem de reserva ----------
   Se uma foto não existir (ex.: img/standard-1.jpg ainda não enviada),
   mostra a logo no lugar em vez de uma imagem quebrada. */
const PLACEHOLDER = 'logo.png';

const usePlaceholder = (img) => {
  if (!img.getAttribute('src') || img.src.endsWith(PLACEHOLDER)) return;
  img.src = PLACEHOLDER;
  img.classList.add('is-placeholder');
};

document.addEventListener('error', (e) => {
  if (e.target instanceof HTMLImageElement) usePlaceholder(e.target);
}, true);

document.querySelectorAll('img').forEach((img) => {
  if (img.complete && img.naturalWidth === 0) usePlaceholder(img);
});

/* ---------- Menu mobile ---------- */
const menuToggle = document.getElementById('menuToggle');
const navLinks = document.getElementById('navLinks');

if (menuToggle && navLinks) {
  const isOpen = () => menuToggle.getAttribute('aria-expanded') === 'true';

  const setMenu = (open) => {
    navLinks.classList.toggle('active', open);
    menuToggle.classList.toggle('active', open);
    menuToggle.setAttribute('aria-expanded', open);
    menuToggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    document.body.classList.toggle('menu-open', open);
    if (open) navLinks.querySelector('a').focus({ preventScroll: true });
  };

  menuToggle.addEventListener('click', () => setMenu(!isOpen()));

  // Clicar em um link, fora do menu, ou apertar Esc fecha
  document.addEventListener('click', (e) => {
    if (isOpen() && (e.target.closest('#navLinks a') || !e.target.closest('.navbar'))) setMenu(false);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isOpen()) {
      setMenu(false);
      menuToggle.focus();
    }
  });

  // Voltou para a tela grande: fecha o menu
  matchMedia('(min-width: 769px)').addEventListener('change', (e) => {
    if (e.matches) setMenu(false);
  });
}

/* ---------- Título animado (letra por letra) ----------
   O texto completo continua acessível via aria-label. */
if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
  let delay = 0.3;

  document.querySelectorAll('.animate-text').forEach((el) => {
    const words = el.textContent.trim().split(' ');
    el.setAttribute('aria-label', words.join(' '));
    el.textContent = '';

    words.forEach((word, i) => {
      const wordEl = document.createElement('span');
      wordEl.className = 'word';
      wordEl.setAttribute('aria-hidden', 'true');

      for (const letter of word) {
        const charEl = document.createElement('span');
        charEl.className = 'char';
        charEl.textContent = letter;
        charEl.style.animationDelay = `${(delay += 0.03)}s`;
        wordEl.append(charEl);
      }

      el.append(wordEl, i < words.length - 1 ? ' ' : '');
    });
  });
}

/* ---------- Galeria com imagem ampliada ----------
   Usa <dialog>: o navegador cuida do foco, do Esc e do fundo bloqueado. */
const photos = [...document.querySelectorAll('.photo')];

if (photos.length) {
  const lightbox = document.createElement('dialog');
  lightbox.className = 'lightbox';
  lightbox.setAttribute('aria-label', 'Imagem ampliada');
  lightbox.innerHTML = `
    <button class="lightbox-close" type="button" aria-label="Fechar imagem">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
    <img alt="">
    <p class="sr-only">Use as setas esquerda e direita para navegar. Escape fecha a visualização.</p>
    <p class="sr-only" role="status"></p>`;
  document.body.append(lightbox);

  const image = lightbox.querySelector('img');
  const status = lightbox.querySelector('[role="status"]');
  let current = 0;

  const show = (index) => {
    current = (index + photos.length) % photos.length;
    const thumb = photos[current].querySelector('img');
    image.src = photos[current].dataset.fullImage || thumb.currentSrc || thumb.src;
    image.alt = thumb.alt;
    status.textContent = `Imagem ${current + 1} de ${photos.length}: ${thumb.alt}`;
  };

  photos.forEach((photo, i) => {
    photo.setAttribute('aria-label', `Ampliar foto: ${photo.querySelector('img').alt}`);
    photo.addEventListener('click', () => {
      show(i);
      lightbox.showModal();
    });
  });

  // Clique no fundo ou no botão fecha
  lightbox.addEventListener('click', (e) => {
    if (e.target === lightbox || e.target.closest('.lightbox-close')) lightbox.close();
  });

  lightbox.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') show(current - 1);
    if (e.key === 'ArrowRight') show(current + 1);
  });
}

/* ---------- Carrossel das atrações (página Torres) ----------
   Fotos em img/torres/<ponto>-1.jpg, -2.jpg, -3.jpg.
   Se alguma não existir, a logo aparece no lugar (ver "Imagem de reserva"). */
const spotButtons = document.querySelectorAll('[data-spot]');

if (spotButtons.length) {
  const PHOTO_DIR = 'img/torres/';

  const SPOTS = {
    molhes: {
      title: 'Praia dos Molhes',
      distance: 'A poucos minutos da pousada',
      text: 'Ponto onde o Rio Mampituba encontra o mar, ladeado pelos molhes de pedra. É ótima para caminhar, pescar, acompanhar o movimento das ondas e ver o pôr do sol.',
      photos: ['Vista da praia e dos molhes', 'Encontro do rio com o mar', 'Fim de tarde na orla']
    },
    grande: {
      title: 'Praia Grande',
      distance: 'Centro de Torres',
      text: 'Faixa de areia ampla e bem estruturada, com quiosques, calçadão e espaço para jogos, esportes e dias de sol em família ou com amigos.',
      photos: ['Faixa de areia da Praia Grande', 'Movimento no verão', 'Calçadão e quiosques']
    },
    farol: {
      title: 'Morro do Farol',
      distance: 'Região central',
      text: 'Mirante natural com vista panorâmica da cidade, do rio e do litoral. Um dos melhores lugares de Torres para fotos e para o pôr do sol.',
      photos: ['Vista do alto do morro', 'Panorama da cidade e da costa', 'Pôr do sol no mirante']
    },
    mampituba: {
      title: 'Orla do Mampituba',
      distance: 'Próximo à pousada',
      text: 'Passeio às margens do rio, com calçadão, mirantes e clima tranquilo. Boa pedida para caminhadas, passeios de bicicleta e para relaxar no fim do dia.',
      photos: ['Calçadão à beira do rio', 'Mirantes da orla', 'Rio Mampituba ao entardecer']
    },
    guarita: {
      title: 'Parque da Guarita',
      distance: 'Sul de Torres',
      text: 'Cartão-postal da cidade, com formações rochosas junto ao mar, trilhas curtas e mirantes com vistas incríveis da costa.',
      photos: ['Formações rochosas à beira-mar', 'Trilhas e mirantes', 'Vista do oceano']
    },
    gastronomia: {
      title: 'Gastronomia local',
      distance: 'Centro e orla',
      text: 'Restaurantes e bares com frutos do mar frescos, pratos da culinária gaúcha e opções para todos os gostos, do almoço de praia ao jantar mais elaborado.',
      photos: ['Frutos do mar', 'Culinária gaúcha', 'Restaurantes e bares da região']
    }
  };

  const spotModal = document.createElement('dialog');
  spotModal.className = 'spot-modal';
  spotModal.setAttribute('aria-labelledby', 'spotTitle');
  spotModal.innerHTML = `
    <div class="spot-box">
      <button class="spot-close" type="button" aria-label="Fechar">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
      <div class="spot-carousel" role="group" aria-roledescription="carrossel" aria-label="Fotos do local">
        <div class="spot-viewport"><div class="spot-track"></div></div>
        <button class="spot-nav spot-prev" type="button" aria-label="Foto anterior">
          <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        </button>
        <button class="spot-nav spot-next" type="button" aria-label="Próxima foto">
          <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </button>
        <div class="spot-dots"></div>
      </div>
      <div class="spot-info">
        <p class="spot-count"></p>
        <h3 id="spotTitle"></h3>
        <p class="spot-text"></p>
        <p class="spot-distance"></p>
      </div>
      <p class="sr-only" role="status" aria-live="polite"></p>
    </div>`;
  document.body.append(spotModal);

  const $ = (sel) => spotModal.querySelector(sel);
  const track = $('.spot-track');
  const dotsBox = $('.spot-dots');
  const counter = $('.spot-count');
  const spotStatus = $('[role="status"]');
  let spot = null;
  let index = 0;

  const goTo = (i) => {
    const total = spot.photos.length;
    index = (i + total) % total;
    track.style.transform = `translateX(${-index * 100}%)`;
    track.querySelectorAll('.spot-slide').forEach((s, n) => s.setAttribute('aria-hidden', n !== index));
    dotsBox.querySelectorAll('.spot-dot').forEach((d, n) => d.setAttribute('aria-current', n === index));
    counter.textContent = `Foto ${index + 1} de ${total}`;
    spotStatus.textContent = `Foto ${index + 1} de ${total}: ${spot.photos[index]}`;
  };

  const open = (key) => {
    spot = SPOTS[key];
    if (!spot) return;

    $('#spotTitle').textContent = spot.title;
    $('.spot-text').textContent = spot.text;
    $('.spot-distance').textContent = spot.distance;

    track.replaceChildren(...spot.photos.map((caption, n) => {
      const slide = document.createElement('figure');
      slide.className = 'spot-slide';
      const img = document.createElement('img');
      img.src = `${PHOTO_DIR}${key}-${n + 1}.jpg`;
      img.alt = `${spot.title}: ${caption}`;
      img.loading = n === 0 ? 'eager' : 'lazy';
      const cap = document.createElement('figcaption');
      cap.textContent = caption;
      slide.append(img, cap);
      return slide;
    }));

    dotsBox.replaceChildren(...spot.photos.map((caption, n) => {
      const dot = document.createElement('button');
      dot.className = 'spot-dot';
      dot.type = 'button';
      dot.setAttribute('aria-label', `Ir para a foto ${n + 1}`);
      dot.addEventListener('click', () => goTo(n));
      return dot;
    }));

    const single = spot.photos.length < 2;
    spotModal.querySelectorAll('.spot-nav, .spot-dots').forEach((el) => { el.hidden = single; });

    track.style.transition = 'none';
    goTo(0);
    track.offsetWidth; // força o layout antes de reativar a animação
    track.style.transition = '';
    spotModal.showModal();
  };

  spotButtons.forEach((btn) => btn.addEventListener('click', () => open(btn.dataset.spot)));

  $('.spot-prev').addEventListener('click', () => goTo(index - 1));
  $('.spot-next').addEventListener('click', () => goTo(index + 1));

  // Clique no fundo ou no botão fecha
  spotModal.addEventListener('click', (e) => {
    if (e.target === spotModal || e.target.closest('.spot-close')) spotModal.close();
  });

  spotModal.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') goTo(index - 1);
    if (e.key === 'ArrowRight') goTo(index + 1);
  });

  // Arrastar com o dedo (celular)
  let startX = null;
  const viewport = $('.spot-viewport');
  viewport.addEventListener('pointerdown', (e) => { startX = e.clientX; });
  viewport.addEventListener('pointerup', (e) => {
    if (startX !== null && Math.abs(e.clientX - startX) > 50) goTo(index + (e.clientX < startX ? 1 : -1));
    startX = null;
  });
  viewport.addEventListener('pointercancel', () => { startX = null; });
}

/* ---------- Preços dos quartos ----------
   Os valores ficam em data/precos.json e são editados pelo painel (admin/).
   Se o arquivo não carregar, a página continua mostrando "Valores sob consulta". */
const priceBoxes = document.querySelectorAll('[data-room-price]');
const priceNotes = document.querySelectorAll('[data-price-note]');

if (priceBoxes.length || priceNotes.length) {
  const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const brlRound = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 0, maximumFractionDigits: 0 });
  const money = (value) => (Number.isInteger(value) ? brlRound : brl).format(value);

  const part = (tag, className, text) => {
    const el = document.createElement(tag);
    el.className = className;
    el.textContent = text;
    return el;
  };

  fetch('data/precos.json', { cache: 'no-cache' })
    .then((res) => (res.ok ? res.json() : Promise.reject(new Error(res.status))))
    .then((data) => {
      priceBoxes.forEach((box) => {
        const entry = data.quartos && data.quartos[box.dataset.roomPrice];
        const value = entry ? Number(entry.preco) : NaN;
        if (!Number.isFinite(value) || value <= 0) return;
        box.replaceChildren(
          part('span', 'price-from', 'a partir de'),
          part('strong', 'price-value', money(value)),
          part('span', 'price-unit', '/ diária')
        );
      });

      const note = typeof data.observacao === 'string' ? data.observacao.trim() : '';
      if (note) {
        priceNotes.forEach((el) => {
          el.textContent = note;
          el.hidden = false;
        });
      }
    })
    .catch(() => { /* mantém o texto padrão */ });
}

/* ---------- Ano do rodapé ---------- */
const year = document.getElementById('currentYear');
if (year) year.textContent = new Date().getFullYear();
