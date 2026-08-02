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

  el.querySelector('.img-slider-prev').addEventListener('click', (e) => { e.stopPropagation(); goTo(current - 1); });
  el.querySelector('.img-slider-next').addEventListener('click', (e) => { e.stopPropagation(); goTo(current + 1); });

  let tx = 0;
  track.addEventListener('touchstart', e => { tx = e.changedTouches[0].clientX; }, { passive: true });
  track.addEventListener('touchend', e => {
    const diff = tx - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) goTo(diff > 0 ? current + 1 : current - 1);
  }, { passive: true });
}
// Callable so dynamically rendered cards can be wired up too. The flag stops a
// second listener being attached if a root is passed twice.
function initSliders(root) {
  (root || document).querySelectorAll('[data-slider]').forEach(function(el) {
    if (el.dataset.sliderReady === '1') return;
    el.dataset.sliderReady = '1';
    initImgSlider(el);
  });
}

initSliders(document);

// --- Gallery Carousel (transform-based sliding) ---
// A function rather than an IIFE: the gallery markup is rendered from the API,
// so this has to run after that render, not at parse time.
var galleryCarouselReady = false;

function initGalleryCarousel() {
  const track = document.getElementById('galleryTrack');
  if (!track) return;
  // İkinci bir render'da filtre butonlarına ve oklara ikinci dinleyici
  // bağlanırsa her tıklama iki sayfa atlardı.
  if (galleryCarouselReady) return;
  galleryCarouselReady = true;

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
      item.setAttribute('tabindex', '-1');
    });
    filtered.forEach((item, i) => {
      item.style.display = 'block';
      item.style.width = itemW + 'px';
      // Yalnızca o an ekranda olan kareler tab sırasında kalır. Aksi hâlde
      // kaydırılmış kareye odaklanmak tarayıcıyı overflow konteynerini
      // scrollLeft ile kaydırmaya iter ve carousel'in transform hesabı bozulur.
      const sayfada = i >= currentPage * pv && i < (currentPage + 1) * pv;
      item.setAttribute('tabindex', sayfada ? '0' : '-1');
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

  document.querySelector('.gallery-car-prev').addEventListener('click', prevPage);
  document.querySelector('.gallery-car-next').addEventListener('click', nextPage);

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
}

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

// --- Google Reviews + Reviews Carousel ---
(function() {
  const track = document.getElementById('reviewsTrack');
  const dotsWrap = document.getElementById('carouselDots');
  if (!track) return;

  const scoreEl = document.querySelector('.gr-score');
  const countEl = document.querySelector('.gr-count');
  const starsEl = document.querySelector('.gr-stars');
  const prevBtn = document.querySelector('.carousel-prev');
  const nextBtn = document.querySelector('.carousel-next');
  let current = 0;

  function getPerView() {
    return window.innerWidth <= 640 ? 1 : window.innerWidth <= 1100 ? 2 : 3;
  }

  function getCards() {
    return Array.from(track.querySelectorAll('.testimonial-card'));
  }

  function getMaxIndex() {
    return Math.max(0, getCards().length - getPerView());
  }

  function starsHtml(rating) {
    const value = Math.max(0, Math.min(5, Math.round(Number(rating) || 0)));
    return Array.from({ length: 5 }, (_, i) =>
      '<i class="fa-solid ' + (i < value ? 'fa-star' : 'fa-star" style="opacity:0.22') + '"></i>'
    ).join('');
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function(ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }

  function avatarColor(name) {
    const colors = ['#1a73e8', '#e8710a', '#0d652d', '#a142f4', '#e91e63', '#0f766e'];
    let sum = 0;
    String(name || '').split('').forEach(ch => { sum += ch.charCodeAt(0); });
    return colors[sum % colors.length];
  }

  function reviewCard(review) {
    const name = escapeHtml(review.name || 'Google kullanıcısı');
    const initial = escapeHtml(review.initial || name.charAt(0) || 'G');
    const time = escapeHtml(review.relative_time || '');
    const text = escapeHtml(review.text || '');
    const rating = Number(review.rating) || 0;
    const photo = review.profile_photo_url ? escapeHtml(review.profile_photo_url) : '';
    const avatar = photo
      ? '<img src="' + photo + '" alt="" loading="lazy" decoding="async">'
      : initial;

    return (
      '<div class="testimonial-card">' +
        '<div class="tc-top">' +
          '<div class="tc-google"><i class="fa-brands fa-google" style="color:#4285F4;"></i></div>' +
          '<div class="testimonial-stars">' + starsHtml(rating) + '</div>' +
        '</div>' +
        '<p class="testimonial-text">"' + text + '"</p>' +
        '<div class="testimonial-author">' +
          '<div class="testimonial-avatar" style="background:' + avatarColor(name) + ';">' + avatar + '</div>' +
          '<div><div class="author-name">' + name + '</div><div class="author-vehicle"><i class="fa-brands fa-google" style="color:#4285F4; font-size:0.65rem;"></i> ' + time + '</div></div>' +
        '</div>' +
      '</div>'
    );
  }

  function renderReviews(data) {
    if (!data || !Array.isArray(data.reviews) || data.reviews.length === 0) return false;

    track.innerHTML = data.reviews.map(reviewCard).join('');
    if (scoreEl && data.average_rating) scoreEl.textContent = Number(data.average_rating).toFixed(1);
    if (countEl && data.total_review_count) countEl.textContent = data.total_review_count + ' Google Yorumu';
    if (starsEl && data.average_rating) starsEl.innerHTML = starsHtml(Math.round(Number(data.average_rating)));
    return true;
  }

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
    const cardEl = getCards()[0];
    if (!cardEl) return;
    const style = window.getComputedStyle(track);
    const gap = parseFloat(style.gap) || 24;
    const offset = current * (cardEl.offsetWidth + gap);
    track.style.transform = `translateX(-${offset}px)`;
    updateDots();
  }

  function next() { goTo(current >= getMaxIndex() ? 0 : current + 1); }
  function prev() { goTo(current <= 0 ? getMaxIndex() : current - 1); }

  if (prevBtn) prevBtn.addEventListener('click', prev);
  if (nextBtn) nextBtn.addEventListener('click', next);

  // Touch/swipe
  let tx = 0;
  track.addEventListener('touchstart', e => { tx = e.changedTouches[0].clientX; }, { passive: true });
  track.addEventListener('touchend', e => {
    const diff = tx - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) { diff > 0 ? next() : prev(); }
  }, { passive: true });

  function initCarousel() {
    current = 0;
    buildDots();
    goTo(0);
  }

  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => { buildDots(); goTo(0); }, 200);
  });

  // No invented reviews: the carousel and the rating badge stay hidden until
  // real Google data arrives. The heading and the "see them on Google" link
  // are left alone so the section still points somewhere useful on failure.
  function revealReviews() {
    const section = track.closest('section');
    if (!section) return;
    ['.reviews-carousel-wrap', '.carousel-dots', '.google-rating-badge']
      .forEach(sel => {
        const el = section.querySelector(sel);
        if (el) el.hidden = false;
      });
  }

  fetch('api/reviews.php?limit=5', { cache: 'no-store' })
    .then(response => response.ok ? response.json() : Promise.reject(response))
    .then(data => {
      if (renderReviews(data)) { revealReviews(); initCarousel(); }
    })
    .catch(err => { console.warn('Google yorumları yüklenemedi:', err); });
})();

// --- Image Mini Carousels ---
document.querySelectorAll('.img-carousel').forEach(function(carousel) {
  var track = carousel.querySelector('.img-carousel-track');
  if (!track) return;
  var imgs = Array.from(track.querySelectorAll('img'));
  var total = imgs.length;
  if (total <= 1) return;

  var current = 0;

  // Prev/next buttons
  var prevBtn = document.createElement('button');
  prevBtn.className = 'img-carousel-btn img-carousel-prev';
  prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
  prevBtn.setAttribute('aria-label', 'Önceki fotoğraf');

  var nextBtn = document.createElement('button');
  nextBtn.className = 'img-carousel-btn img-carousel-next';
  nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
  nextBtn.setAttribute('aria-label', 'Sonraki fotoğraf');

  carousel.appendChild(prevBtn);
  carousel.appendChild(nextBtn);

  // Dots
  var dotsWrap = document.createElement('div');
  dotsWrap.className = 'img-carousel-dots';
  imgs.forEach(function(_, i) {
    var dot = document.createElement('button');
    dot.className = 'img-carousel-dot' + (i === 0 ? ' active' : '');
    dot.setAttribute('aria-label', (i + 1) + '. fotoğraf');
    dot.addEventListener('click', function(e) { e.stopPropagation(); goTo(i); });
    dotsWrap.appendChild(dot);
  });
  carousel.appendChild(dotsWrap);

  function goTo(idx) {
    current = (idx + total) % total;
    track.style.transform = 'translateX(-' + (current * 100) + '%)';
    dotsWrap.querySelectorAll('.img-carousel-dot').forEach(function(dot, i) {
      dot.classList.toggle('active', i === current);
    });
  }

  // Click on image → next photo
  track.addEventListener('click', function() { goTo(current + 1); });

  prevBtn.addEventListener('click', function(e) { e.stopPropagation(); goTo(current - 1); });
  nextBtn.addEventListener('click', function(e) { e.stopPropagation(); goTo(current + 1); });

  // Touch swipe
  var touchStartX = 0;
  carousel.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].clientX;
  }, { passive: true });
  carousel.addEventListener('touchend', function(e) {
    var diff = touchStartX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) { diff > 0 ? goTo(current + 1) : goTo(current - 1); }
  }, { passive: true });
});

// --- Gallery Lightbox ---
// Guard against a second lightbox being appended to the body: the gallery can
// be rendered from the API, and this must not build its DOM twice.
var galleryLightboxReady = false;

function initGalleryLightbox() {
  if (galleryLightboxReady) return;

  var items = Array.from(document.querySelectorAll('.gallery-item'));
  if (!items.length) return;

  galleryLightboxReady = true;

  // Build lightbox DOM
  var lb = document.createElement('div');
  lb.className = 'gallery-lightbox';
  lb.setAttribute('role', 'dialog');
  lb.setAttribute('aria-modal', 'true');
  lb.innerHTML =
    '<div class="gallery-lightbox-inner">' +
      '<span class="gallery-lightbox-count"></span>' +
      '<button class="gallery-lightbox-close" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button>' +
      '<img src="" alt="" draggable="false">' +
      '<p class="gallery-lightbox-caption"></p>' +
      '<button class="gallery-lightbox-btn gallery-lightbox-prev" aria-label="Önceki"><i class="fa-solid fa-chevron-left"></i></button>' +
      '<button class="gallery-lightbox-btn gallery-lightbox-next" aria-label="Sonraki"><i class="fa-solid fa-chevron-right"></i></button>' +
    '</div>';
  // Kapalıyken erişilemez: aksi hâlde içindeki üç buton galeri sayfasının tab
  // sırasında görünmez duraklar olarak kalır.
  lb.setAttribute('inert', '');
  document.body.appendChild(lb);

  var lbImg      = lb.querySelector('img');
  var lbCaption  = lb.querySelector('.gallery-lightbox-caption');
  var lbCount    = lb.querySelector('.gallery-lightbox-count');
  var lbClose    = lb.querySelector('.gallery-lightbox-close');
  var lbPrev     = lb.querySelector('.gallery-lightbox-prev');
  var lbNext     = lb.querySelector('.gallery-lightbox-next');

  var visibleItems = [];
  var current = 0;

  function getVisible() {
    return items.filter(function(item) { return item.style.display !== 'none'; });
  }

  function showCurrent() {
    var item = visibleItems[current];
    // Görünür kare kalmadıysa (filtre + klavye ile tetiklenebiliyor) açma.
    if (!item) { close(); return; }

    var img  = item.querySelector('img');
    var cap  = item.querySelector('.gallery-overlay span');
    lbImg.src = img ? (img.getAttribute('data-full') || img.getAttribute('src')).replace(/\?.*/, '') : '';
    lbImg.alt = img ? img.alt : '';
    // data-caption panelde girilen açıklamadır; yoksa karenin etiketine düşülür.
    lbCaption.textContent = item.getAttribute('data-caption') || (cap ? cap.textContent : '');
    lbCount.textContent = (current + 1) + ' / ' + visibleItems.length;
    var single = visibleItems.length <= 1;
    lbPrev.style.display = single ? 'none' : '';
    lbNext.style.display = single ? 'none' : '';
  }

  // Kapanışta odağın nereye döneceğini bilmek için açan eleman saklanır.
  var acanEleman = null;

  function openAt(idx) {
    visibleItems = getVisible();
    if (!visibleItems.length) return;

    current = Math.max(0, Math.min(idx, visibleItems.length - 1));
    acanEleman = document.activeElement;
    lb.classList.add('open');
    lb.removeAttribute('inert');
    showCurrent();
    document.body.style.overflow = 'hidden';
    // Odak diyaloğa taşınmazsa ekran okuyucu kullanıcısı aria-modal yüzünden
    // arka planı da duyamaz, açık bir diyalogda kaybolur.
    lbClose.focus();
  }

  function close() {
    lb.classList.remove('open');
    // Kapalı diyalog görsel olarak gizli ama DOM'da; inert olmadan Kapat/Önceki/
    // Sonraki butonları sayfanın tab sırasında görünmez duraklar olarak kalırdı.
    lb.setAttribute('inert', '');
    document.body.style.overflow = '';

    if (acanEleman && typeof acanEleman.focus === 'function') {
      acanEleman.focus();
      acanEleman = null;
    }
  }

  function next() { current = current >= visibleItems.length - 1 ? 0 : current + 1; showCurrent(); }
  function prev() { current = current <= 0 ? visibleItems.length - 1 : current - 1; showCurrent(); }

  // Open on gallery item click
  items.forEach(function(item) {
    function ac() {
      visibleItems = getVisible();
      var idx = visibleItems.indexOf(item);
      if (idx >= 0) openAt(idx);
    }

    item.addEventListener('click', ac);

    // Kareler role="button" tabindex="0" taşıyor; klavye kullanıcısı da
    // açabilmeli. Space sayfayı kaydırmasın diye varsayılan engelleniyor.
    item.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
        e.preventDefault();
        ac();
      }
    });
  });

  lbClose.addEventListener('click', close);
  lbPrev.addEventListener('click', function(e) { e.stopPropagation(); prev(); });
  lbNext.addEventListener('click', function(e) { e.stopPropagation(); next(); });

  // Click outside the inner panel → close; click on image → next
  lb.addEventListener('click', function(e) { if (e.target === lb) close(); });
  lbImg.addEventListener('click', next);

  // Keyboard navigation
  document.addEventListener('keydown', function(e) {
    if (!lb.classList.contains('open')) return;
    if (e.key === 'Escape')     close();
    if (e.key === 'ArrowRight') next();
    if (e.key === 'ArrowLeft')  prev();
  });

  // Touch swipe in lightbox
  var touchStartX = 0;
  lb.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].clientX;
  }, { passive: true });
  lb.addEventListener('touchend', function(e) {
    var diff = touchStartX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) { diff > 0 ? next() : prev(); }
  }, { passive: true });
}

// --- Galeri Listesi ---
(function() {
  var track = document.getElementById('galleryTrack');
  if (!track) return;

  var stateEl = document.getElementById('galleryState');

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function(ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }

  function showState(message) {
    if (!stateEl) return;
    // Önce görünür yapılır: hidden bir alt ağaçtaki metin değişimi canlı bölge
    // olarak duyurulmaz, sıra tersine olsaydı mesaj sessiz kalabilirdi.
    stateEl.hidden = false;
    stateEl.textContent = message;
  }

  function clearState() {
    if (!stateEl) return;
    stateEl.textContent = '';
    stateEl.hidden = true;
  }

  function itemHtml(item) {
    var image = item.image || {};
    var src = escapeHtml(image.src);
    var srcset = image.srcLarge
      ? ' srcset="' + src + ' 800w, ' + escapeHtml(image.srcLarge) + ' 1400w" sizes="(max-width: 640px) 82vw, 300px"'
      : '';
    var full = image.srcLarge ? ' data-full="' + escapeHtml(image.srcLarge) + '"' : '';

    // role/tabindex: kareler fare dışında da açılabilmeli. Lightbox'ı açan
    // dinleyici .gallery-item üzerinde, bu yüzden odak da orada olmalı.
    // data-caption lightbox alt yazısıdır; overlay span'i kısa başlığı taşır ve
    // panelde girilen açıklama olmadan lightbox'a hiç ulaşamazdı.
    return '<div class="gallery-item" data-cat="' + escapeHtml(item.category) + '"'
        + ' data-caption="' + escapeHtml(item.caption || item.title) + '"'
        + ' role="button" tabindex="0" aria-label="' + escapeHtml(item.caption || item.title) + ' — büyüt">' +
        '<div class="gallery-inner has-photo">' +
          '<img src="' + src + '" alt="' + escapeHtml(image.alt || item.title || '') + '" loading="lazy"' + srcset + ' decoding="async"' + full + '>' +
        '</div>' +
        '<div class="gallery-overlay">' +
          '<i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i>' +
          '<span>' + escapeHtml(item.title) + '</span>' +
        '</div>' +
      '</div>';
  }

  // Sayımı sıfır olan filtre hiçbir şey göstermez; butonu bırakmak boş bir
  // galeriye tıklatmak olurdu. "Tümü" her zaman kalır.
  function syncFilters(categories) {
    if (!Array.isArray(categories)) return;

    var sayim = {};
    categories.forEach(function(c) { sayim[c.key] = c.count; });

    document.querySelectorAll('.filter-btn').forEach(function(btn) {
      var filter = btn.dataset.filter;
      if (filter && filter !== 'all') {
        btn.hidden = !sayim[filter];
      }
    });
  }

  function render(data) {
    var items = (data && Array.isArray(data.items)) ? data.items : [];

    if (!items.length) {
      track.innerHTML = '';
      // Boş galeri bir hata değil: panelden tüm kareler silinmiş olabilir.
      showState('Galeride henüz kare yok.');
      syncFilters(data && data.categories);
      track.setAttribute('aria-busy', 'false');
      return;
    }

    track.innerHTML = items.map(itemHtml).join('');
    clearState();
    syncFilters(data.categories);

    // Sıra önemli: carousel öğelerin display değerini yazar, lightbox
    // görünürlüğü ondan okur.
    initGalleryCarousel();
    initGalleryLightbox();

    track.setAttribute('aria-busy', 'false');
  }

  track.setAttribute('aria-busy', 'true');
  showState('Galeri yükleniyor…');

  fetch('api/galeri.php', { cache: 'no-store' })
    .then(function(response) { return response.ok ? response.json() : Promise.reject(response); })
    .then(function(data) {
      if (data && data.success === false) return Promise.reject(data);
      render(data);
    })
    .catch(function(err) {
      console.warn('Galeri yüklenemedi:', err);
      track.innerHTML = '';
      showState('Galeri şu an yüklenemedi. Lütfen sayfayı yenileyin.');
      track.setAttribute('aria-busy', 'false');
    });
})();

// --- Hizmetler Listesi ---
// index.html özet kartı, hizmetler.html detay kartı basar; ikisi de aynı ucu
// okur, fark yalnızca konteynerin data-variant değeridir.
(function() {
  var grid = document.getElementById('servicesGrid');
  if (!grid) return;

  var stateEl = document.getElementById('servicesState');
  var variant = grid.getAttribute('data-variant') === 'detail' ? 'detail' : 'summary';
  var SIZES = '(max-width: 900px) 100vw, 360px';

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function(ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }

  function showState(message) {
    if (!stateEl) return;
    // Önce görünür yapılır: hidden bir alt ağaçtaki metin değişimi canlı bölge
    // olarak duyurulmaz, sıra tersine olsaydı mesaj sessiz kalabilirdi.
    stateEl.hidden = false;
    stateEl.textContent = message;
  }

  function clearState() {
    if (!stateEl) return;
    stateEl.textContent = '';
    stateEl.hidden = true;
  }

  // İkon adı sunucuda beyaz listeden geçiyor; burada ikinci bir biçim kontrolü
  // var, çünkü bu değer doğrudan class özniteliğine giriyor.
  function iconClass(icon) {
    return /^fa-[a-z0-9-]+$/.test(String(icon || '')) ? String(icon) : '';
  }

  function imageTag(image, cls) {
    var src = escapeHtml(image.src);
    // srcset yalnızca ikinci boyut gerçekten varsa basılır; yoksa tarayıcıya
    // olmayan bir 1400w kaynağı vaat edilmiş olurdu.
    var srcset = image.srcLarge
      ? ' srcset="' + src + ' 800w, ' + escapeHtml(image.srcLarge) + ' 1400w" sizes="' + SIZES + '"'
      : '';
    var full = image.srcLarge ? ' data-full="' + escapeHtml(image.srcLarge) + '"' : '';

    return '<img src="' + src + '" alt="' + escapeHtml(image.alt || '') + '" class="' + cls + '"'
      + ' loading="lazy"' + srcset + ' decoding="async"' + full + '>';
  }

  // Tek görselde slider markup'ı hiç basılmaz: initImgSlider zaten tek slide'da
  // devreye girmiyor ve butonlarla noktalar ölü kalıntı olarak görünürdü.
  function mediaHtml(images) {
    if (!images.length) return '';

    if (images.length === 1) {
      return '<div class="service-detail-img-wrap">' + imageTag(images[0], 'service-detail-img') + '</div>';
    }

    return '<div class="img-slider" data-slider>' +
        '<div class="img-slider-track">' +
          images.map(function(im) { return imageTag(im, 'img-slider-slide'); }).join('') +
        '</div>' +
        '<button class="img-slider-btn img-slider-prev" aria-label="Önceki"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>' +
        '<button class="img-slider-btn img-slider-next" aria-label="Sonraki"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>' +
        '<div class="img-slider-dots"></div>' +
      '</div>';
  }

  function summaryCard(service, index) {
    return '<div class="service-detail-card reveal reveal-d' + ((index % 3) + 1) + '">' +
        mediaHtml(service.images || []) +
        '<div class="service-detail-body">' +
          '<div class="card-brand" style="font-size:0.75rem; color:#888; letter-spacing:1px; margin-bottom:8px; font-weight:600;">DRN_EKİN OTO</div>' +
          '<h3 style="margin-bottom:12px; color:#1a1a2e; font-size:1.25rem;">' + escapeHtml(service.title) + '</h3>' +
          '<p style="font-size:0.95rem; color:#555; margin-bottom:20px;">' + escapeHtml(service.summary) + '</p>' +
          '<a href="hizmetler.html" class="btn-detail" style="display:flex; justify-content:space-between; align-items:center; text-decoration:none; color:#e63946; font-weight:600; font-size:0.9rem; border-top:1px solid #eee; padding-top:12px;">Detaylı İncele <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>' +
        '</div>' +
      '</div>';
  }

  function detailCard(service, index) {
    var icon = iconClass(service.icon);
    var header = '<div class="service-detail-header">' +
        (icon ? '<div class="service-detail-icon"><i class="fa-solid ' + icon + '" aria-hidden="true"></i></div>' : '') +
        '<h3>' + escapeHtml(service.title) + '</h3>' +
      '</div>';

    var features = (service.features || []).length
      ? '<ul class="service-feature-list">' + service.features.map(function(f) {
          return '<li class="service-feature-item"><i class="fa-solid fa-check" aria-hidden="true"></i> ' + escapeHtml(f) + '</li>';
        }).join('') + '</ul>'
      : '';

    return '<div class="service-detail-card reveal reveal-d' + ((index % 3) + 1) + '">' +
        mediaHtml(service.images || []) +
        header +
        '<div class="service-detail-body"><p>' + escapeHtml(service.description) + '</p>' + features + '</div>' +
      '</div>';
  }

  function render(data) {
    var services = (data && Array.isArray(data.services)) ? data.services : [];

    if (!services.length) {
      grid.innerHTML = '';
      showState('Hizmetler şu an görüntülenemiyor.');
    } else {
      var card = variant === 'detail' ? detailCard : summaryCard;
      grid.innerHTML = services.map(card).join('');
      clearState();
      initSliders(grid);
      // Bu olmadan kartlar .reveal'in opacity:0 değerinde kalır ve görünmez.
      grid.querySelectorAll('.service-detail-card').forEach(function(el) {
        revealObserver.observe(el);
      });
    }

    grid.setAttribute('aria-busy', 'false');
  }

  grid.setAttribute('aria-busy', 'true');
  showState('Hizmetler yükleniyor…');

  fetch('api/hizmetler.php', { cache: 'no-store' })
    .then(function(response) { return response.ok ? response.json() : Promise.reject(response); })
    .then(function(data) {
      if (data && data.success === false) return Promise.reject(data);
      render(data);
    })
    .catch(function(err) {
      console.warn('Hizmetler yüklenemedi:', err);
      grid.innerHTML = '';
      showState('Hizmetler şu an yüklenemedi. Lütfen sayfayı yenileyin.');
      grid.setAttribute('aria-busy', 'false');
    });
})();

// --- Blog Toggle ---
function toggleBlog(el) {
  var detail = el.closest('.blog-card-body').querySelector('.blog-detail');
  if (!detail) return;
  var isOpen = detail.style.display !== 'none';
  detail.style.display = isOpen ? 'none' : 'block';
  el.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
  el.innerHTML = isOpen
    ? 'Devamını Oku <i class="fa-solid fa-arrow-right"></i>'
    : 'Yazıyı Kapat <i class="fa-solid fa-arrow-up"></i>';
}

// --- Blog List ---
(function() {
  var list = document.getElementById('blogList');
  if (!list) return;

  var catsList = document.getElementById('blogCats');
  var stateEl = document.getElementById('blogState');

  // The reviews block has its own copy inside its IIFE; reaching into a
  // working block to share it would be a bigger change than these five lines.
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function(ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }

  // Only this element is a live region, so a screen reader announces the short
  // status instead of re-reading every post.
  function showState(message) {
    if (!stateEl) return;
    // Önce görünür yapılır: hidden bir alt ağaçtaki metin değişimi canlı bölge
    // olarak duyurulmaz, sıra tersine olsaydı mesaj sessiz kalabilirdi.
    stateEl.hidden = false;
    stateEl.textContent = message;
  }

  function clearState() {
    if (!stateEl) return;
    stateEl.textContent = '';
    stateEl.hidden = true;
  }

  function hideCategories() {
    if (!catsList) return;
    var widget = catsList.closest('.sidebar-widget');
    if (widget) widget.hidden = true;
  }

  function imageHtml(post) {
    var image = post.image;
    if (!image || !image.src) return '';
    var src = escapeHtml(image.src);
    // srcset is dropped when the server could not produce a second size,
    // otherwise the browser would be told 1400w exists when it does not.
    var srcset = image.srcLarge
      ? ' srcset="' + src + ' 800w, ' + escapeHtml(image.srcLarge) + ' 1400w" sizes="(max-width: 900px) 100vw, 240px"'
      : '';

    return '<div class="blog-card-img">' +
        '<img src="' + src + '" alt="' + escapeHtml(image.alt || post.title || '') + '" loading="lazy" decoding="async"' + srcset + '>' +
        (post.category ? '<span class="blog-cat">' + escapeHtml(post.category) + '</span>' : '') +
      '</div>';
  }

  function cardHtml(post) {
    var hasImage = !!(post.image && post.image.src);
    var dateHtml = post.date
      ? '<time datetime="' + escapeHtml(post.date) + '">' + escapeHtml(post.dateText || post.date) + '</time>'
      : escapeHtml(post.dateText || '');

    var meta = '<span><i class="fa-regular fa-calendar"></i> ' + dateHtml + '</span>' +
      '<span><i class="fa-regular fa-clock"></i> ' + escapeHtml(post.readingMinutes || 1) + ' dk okuma</span>';
    // The category badge lives on the image; without one it would be lost.
    if (!hasImage && post.category) {
      meta += '<span><i class="fa-regular fa-folder"></i> ' + escapeHtml(post.category) + '</span>';
    }

    var detail = post.content
      ? '<div class="blog-detail" style="display:none;">' + post.content + '</div>' +
        '<div class="blog-card-footer">' +
          '<button type="button" class="read-more" onclick="toggleBlog(this)" aria-expanded="false">Devamını Oku <i class="fa-solid fa-arrow-right"></i></button>' +
          '<span style="font-size:0.78rem; color:var(--gray-light);">DRN_EKİN OTO</span>' +
        '</div>'
      : '<div class="blog-card-footer">' +
          '<span style="font-size:0.78rem; color:var(--gray-light);">DRN_EKİN OTO</span>' +
        '</div>';

    return '<div class="blog-card reveal' + (hasImage ? '' : ' blog-card--noimg') + '">' +
        imageHtml(post) +
        '<div class="blog-card-body">' +
          '<div class="blog-meta">' + meta + '</div>' +
          '<h3>' + escapeHtml(post.title || '') + '</h3>' +
          '<p>' + escapeHtml(post.excerpt || '') + '</p>' +
          detail +
        '</div>' +
      '</div>';
  }

  function renderCategories(categories) {
    if (!catsList) return;
    if (!Array.isArray(categories) || !categories.length) {
      hideCategories();
      return;
    }

    catsList.innerHTML = categories.map(function(cat) {
      return '<li class="cat-item"><span>' + escapeHtml(cat.name) + '</span>' +
        '<span class="cat-count">' + escapeHtml(cat.count) + '</span></li>';
    }).join('');
  }

  function render(data) {
    var posts = (data && Array.isArray(data.posts)) ? data.posts : [];

    if (!posts.length) {
      list.innerHTML = '';
      showState('Henüz yazı yayınlanmadı.');
    } else {
      list.innerHTML = posts.map(cardHtml).join('');
      clearState();
      // Without this the cards keep .reveal's opacity:0 and stay invisible.
      list.querySelectorAll('.blog-card').forEach(function(card) {
        revealObserver.observe(card);
      });
    }

    renderCategories(data && data.categories);
    list.setAttribute('aria-busy', 'false');
  }

  // Yükleniyor durumu buradan yazılır; HTML'de sabit dursaydı JS kapalı
  // tarayıcıda sonsuza kadar "yükleniyor" gösterirdi.
  list.setAttribute('aria-busy', 'true');
  showState('Yazılar yükleniyor…');

  fetch('api/blog.php', { cache: 'no-store' })
    .then(function(response) { return response.ok ? response.json() : Promise.reject(response); })
    .then(function(data) {
      if (data && data.success === false) return Promise.reject(data);
      render(data);
    })
    .catch(function(err) {
      console.warn('Blog yazıları yüklenemedi:', err);
      list.innerHTML = '';
      showState('Yazılar şu an yüklenemedi. Lütfen sayfayı yenileyin.');
      list.setAttribute('aria-busy', 'false');
      hideCategories();
    });
})();

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
