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

// --- Gallery Filter ---
const filterBtns = document.querySelectorAll('.filter-btn');
const galleryItems = document.querySelectorAll('.gallery-item');
filterBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    filterBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const filter = btn.dataset.filter;
    galleryItems.forEach(item => {
      if (filter === 'all' || item.dataset.cat === filter) {
        item.style.display = 'block';
        item.style.animation = 'fadeIn 0.4s ease';
      } else {
        item.style.display = 'none';
      }
    });
  });
});

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
    <a href="tel:+905304115022" class="mab-call"><i class="fa-solid fa-phone"></i> Hemen Ara</a>
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
    return window.innerWidth <= 640 ? 1 : window.innerWidth <= 900 ? 2 : 3;
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
