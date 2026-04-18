/* =============================================
   DRN.EKİN OTO - JavaScript
   ============================================= */

// --- Hamburger / Mobile Nav ---
(function() {
  var btn = document.getElementById('hamburger');
  var nav = document.getElementById('mobileNav');
  if (!btn || !nav) return;
  var isOpen = false;

  function navOpen() {
    isOpen = true;
    btn.classList.add('active');
    nav.style.cssText = 'display:block!important;';
  }
  function navClose() {
    isOpen = false;
    btn.classList.remove('active');
    nav.style.cssText = 'display:none!important;';
  }

  navClose();
  btn.addEventListener('click', function(e) { e.stopPropagation(); isOpen ? navClose() : navOpen(); });
  nav.querySelectorAll('a').forEach(function(a) { a.addEventListener('click', navClose); });
  document.addEventListener('click', function(e) {
    if (isOpen && !nav.contains(e.target) && !btn.contains(e.target)) navClose();
  });
})();

// --- Sticky Navbar ---
const navbar = document.getElementById('navbar');
if (navbar) {
  window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 50);
  });
}

// --- Scroll Reveal ---
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('revealed');
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });

document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

// --- Stat Counter Animation ---
function animateCounter(el, target, duration = 2000) {
  let start = 0;
  const step = target / (duration / 16);
  const timer = setInterval(() => {
    start += step;
    if (start >= target) {
      el.textContent = target.toLocaleString('tr-TR') + (el.dataset.suffix || '');
      clearInterval(timer);
    } else {
      el.textContent = Math.floor(start).toLocaleString('tr-TR') + (el.dataset.suffix || '');
    }
  }, 16);
}

const statsObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const counters = entry.target.querySelectorAll('[data-count]');
      counters.forEach(counter => {
        animateCounter(counter, parseInt(counter.dataset.count), 1800);
      });
      statsObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.3 });

const statsSection = document.querySelector('.stats-grid');
if (statsSection) statsObserver.observe(statsSection);

// --- FAQ Accordion ---
document.querySelectorAll('.faq-question').forEach(question => {
  question.addEventListener('click', () => {
    const item = question.closest('.faq-item');
    const answer = item.querySelector('.faq-answer');
    const isActive = item.classList.contains('active');

    document.querySelectorAll('.faq-item.active').forEach(active => {
      active.classList.remove('active');
      active.querySelector('.faq-answer').classList.remove('open');
    });

    if (!isActive) {
      item.classList.add('active');
      answer.classList.add('open');
    }
  });
});

// --- Reusable Image Slider ---
function initImgSlider(el) {
  const track = el.querySelector('.img-slider-track');
  const slides = Array.from(el.querySelectorAll('.img-slider-slide'));
  if (!track || slides.length <= 1) return;
  const dotsWrap = el.querySelector('.img-slider-dots');
  let current = 0;

  if (dotsWrap) {
    slides.forEach((_, i) => {
      const d = document.createElement('button');
      d.className = 'img-slider-dot' + (i === 0 ? ' active' : '');
      d.setAttribute('aria-label', `Görsel ${i + 1}`);
      d.addEventListener('click', () => goTo(i));
      dotsWrap.appendChild(d);
    });
  }

  function goTo(idx) {
    current = ((idx % slides.length) + slides.length) % slides.length;
    track.style.transform = `translateX(-${current * 100}%)`;
    if (dotsWrap) {
      dotsWrap.querySelectorAll('.img-slider-dot').forEach((d, i) =>
        d.classList.toggle('active', i === current)
      );
    }
  }

  el.querySelector('.img-slider-prev')?.addEventListener('click', (e) => { e.stopPropagation(); goTo(current - 1); });
  el.querySelector('.img-slider-next')?.addEventListener('click', (e) => { e.stopPropagation(); goTo(current + 1); });

  let tx = 0;
  track.addEventListener('touchstart', e => { tx = e.changedTouches[0].clientX; }, { passive: true });
  track.addEventListener('touchend', e => {
    const diff = tx - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) goTo(diff > 0 ? current + 1 : current - 1);
  }, { passive: true });
}
document.querySelectorAll('[data-slider]').forEach(initImgSlider);

// --- Gallery Carousel (transform-based sliding) ---
(function() {
  const track = document.getElementById('galleryTrack');
  if (!track) return;
  const overflow = track.parentElement; // .gallery-carousel-overflow
  const dotsWrap = document.getElementById('galleryDots');
  const filterBtns = document.querySelectorAll('.filter-btn');
  const allItems = Array.from(track.querySelectorAll('.gallery-item'));
  const GAP = 18;
  let activeFilter = 'all';
  let currentPage = 0;

  function getPerView() {
    if (window.innerWidth <= 600) return 1;
    if (window.innerWidth <= 960) return 2;
    return 3;
  }

  function getFiltered() {
    return allItems.filter(item => activeFilter === 'all' || item.dataset.cat === activeFilter);
  }

  function render(animate) {
    const pv = getPerView();
    const filtered = getFiltered();
    const maxPage = Math.max(0, Math.ceil(filtered.length / pv) - 1);
    currentPage = Math.min(currentPage, maxPage);

    // Item width fills exactly perView items in the overflow container
    const containerW = overflow.offsetWidth;
    const itemW = (containerW - (pv - 1) * GAP) / pv;

    // Hide non-matching, show matching with correct width
    allItems.forEach(item => {
      item.style.display = 'none';
      item.style.width = '';
    });
    filtered.forEach(item => {
      item.style.display = 'block';
      item.style.width = itemW + 'px';
    });

    // Slide: offset by currentPage * perView items
    if (!animate) track.style.transition = 'none';
    const offset = currentPage * pv * (itemW + GAP);
    track.style.transform = `translateX(-${offset}px)`;
    if (!animate) requestAnimationFrame(() => { track.style.transition = ''; });

    buildDots(filtered.length, pv, maxPage);
  }

  function buildDots(total, pv, maxPage) {
    if (!dotsWrap) return;
    dotsWrap.innerHTML = '';
    if (maxPage < 1) return;
    for (let i = 0; i <= maxPage; i++) {
      const d = document.createElement('button');
      d.className = 'gallery-car-dot' + (i === currentPage ? ' active' : '');
      d.setAttribute('aria-label', `Sayfa ${i + 1}`);
      d.addEventListener('click', () => { currentPage = i; render(true); });
      dotsWrap.appendChild(d);
    }
  }

  function prevPage() {
    const pv = getPerView();
    const maxPage = Math.max(0, Math.ceil(getFiltered().length / pv) - 1);
    currentPage = currentPage <= 0 ? maxPage : currentPage - 1;
    render(true);
  }

  function nextPage() {
    const pv = getPerView();
    const maxPage = Math.max(0, Math.ceil(getFiltered().length / pv) - 1);
    currentPage = currentPage >= maxPage ? 0 : currentPage + 1;
    render(true);
  }

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeFilter = btn.dataset.filter;
      currentPage = 0;
      render(false);
    });
  });

  document.querySelector('.gallery-car-prev')?.addEventListener('click', prevPage);
  document.querySelector('.gallery-car-next')?.addEventListener('click', nextPage);

  let tx = 0;
  track.addEventListener('touchstart', e => { tx = e.changedTouches[0].clientX; }, { passive: true });
  track.addEventListener('touchend', e => {
    const diff = tx - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) { diff > 0 ? nextPage() : prevPage(); }
  }, { passive: true });

  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => { currentPage = 0; render(false); }, 150);
  });

  render(false);
})();

// --- Form Submission ---
document.querySelectorAll('form[data-form] [type="submit"]').forEach(btn => {
  btn.dataset.original = btn.innerHTML;
});

document.querySelectorAll('form[data-form]').forEach(form => {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = form.querySelector('[type="submit"]');
    const successEl = form.nextElementSibling;
    const formType = form.dataset.form;

    if (btn) { btn.disabled = true; btn.textContent = 'Gönderiliyor...'; }

    const inputs = form.querySelectorAll('input, select, textarea');
    const data = new FormData();

    if (formType === 'appointment') {
      data.append('type', 'randevu');
      const fields = ['ad_soyad','telefon','eposta','marka','model','yil','plaka','hizmet','tarih','saat','notlar'];
      inputs.forEach((el, i) => { if (fields[i] !== undefined) data.append(fields[i], el.value); });
    } else if (formType === 'contact') {
      data.append('type', 'iletisim');
      const fields = ['ad_soyad','telefon','eposta','konu','mesaj'];
      inputs.forEach((el, i) => { if (fields[i] !== undefined) data.append(fields[i], el.value); });
    }

    try {
      const res = await fetch('mail.php', { method: 'POST', body: data });
      const json = await res.json();

      if (json.success) {
        form.reset();
        if (successEl && successEl.classList.contains('form-success')) {
          successEl.classList.add('show');
          setTimeout(() => successEl.classList.remove('show'), 6000);
        }
      } else {
        alert(json.message || 'Bir hata oluştu. Lütfen tekrar deneyin.');
      }
    } catch {
      alert('Bağlantı hatası. Lütfen tekrar deneyin.');
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Gönder'; }
    }
  });
});

// --- Mobile Action Bar ---
(function() {
  const bar = document.createElement('div');
  bar.className = 'mobile-action-bar';
  bar.innerHTML = `
    <a href="tel:+905448500802" class="mab-call"><i class="fa-solid fa-phone"></i> Hemen Ara</a>
    <a href="randevu.html" class="mab-randevu"><i class="fa-solid fa-calendar-check"></i> Randevu Al</a>
  `;
  document.body.appendChild(bar);
})();

// --- Reviews Carousel ---
(function() {
  const track = document.getElementById('reviewsTrack');
  const dotsWrap = document.getElementById('carouselDots');
  if (!track) return;

  const cards = Array.from(track.querySelectorAll('.testimonial-card'));
  const total = cards.length;
  let current = 0;
  let autoTimer;

  function getPerView() {
    return window.innerWidth <= 640 ? 1 : window.innerWidth <= 1100 ? 2 : 3;
  }
  function getMaxIndex() { return Math.max(0, total - getPerView()); }

  function buildDots() {
    if (!dotsWrap) return;
    dotsWrap.innerHTML = '';
    for (let i = 0; i <= getMaxIndex(); i++) {
      const d = document.createElement('button');
      d.className = 'carousel-dot' + (i === 0 ? ' active' : '');
      d.addEventListener('click', () => goTo(i));
      dotsWrap.appendChild(d);
    }
  }

  function updateDots() {
    if (!dotsWrap) return;
    dotsWrap.querySelectorAll('.carousel-dot').forEach((d, i) => {
      d.classList.toggle('active', i === current);
    });
  }

  function goTo(idx) {
    current = Math.max(0, Math.min(idx, getMaxIndex()));
    // Calculate offset based on actual rendered card width
    const cardEl = cards[0];
    const style = window.getComputedStyle(track);
    const gap = parseFloat(style.gap) || 24;
    const offset = current * (cardEl.offsetWidth + gap);
    track.style.transform = `translateX(-${offset}px)`;
    updateDots();
  }

  function next() { goTo(current >= getMaxIndex() ? 0 : current + 1); }
  function prev() { goTo(current <= 0 ? getMaxIndex() : current - 1); }

  document.querySelector('.carousel-prev')?.addEventListener('click', prev);
  document.querySelector('.carousel-next')?.addEventListener('click', next);

  // Touch/swipe
  let tx = 0;
  track.addEventListener('touchstart', e => { tx = e.changedTouches[0].clientX; }, { passive: true });
  track.addEventListener('touchend', e => {
    const diff = tx - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) { diff > 0 ? next() : prev(); }
  }, { passive: true });

  buildDots();
  // Auto-scroll kapalı — kullanıcı manuel kaydırır

  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => { buildDots(); goTo(0); }, 200);
  });
})();

// --- Blog Toggle ---
function toggleBlog(el) {
  var detail = el.closest('.blog-card-body').querySelector('.blog-detail');
  if (!detail) return;
  var isOpen = detail.style.display !== 'none';
  detail.style.display = isOpen ? 'none' : 'block';
  el.innerHTML = isOpen
    ? 'Devamını Oku <i class="fa-solid fa-arrow-right"></i>'
    : 'Yazıyı Kapat <i class="fa-solid fa-arrow-up"></i>';
}

// --- Smooth Active Nav Link ---
const currentPage = window.location.pathname.split('/').pop() || 'index.html';
document.querySelectorAll('.nav-links a, .mobile-nav a').forEach(link => {
  const href = link.getAttribute('href');
  if (href === currentPage || (currentPage === '' && href === 'index.html')) {
    link.classList.add('active');
  }
});

// Fade in animation keyframes added via JS
const style = document.createElement('style');
style.textContent = `@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }`;
document.head.appendChild(style);
