// ── Navbar scroll ──
const navbar = document.getElementById('navbar');
let lastScrollY = window.scrollY;
if (navbar) {
  window.addEventListener('scroll', () => {
    const currentScrollY = window.scrollY;
    if (currentScrollY > lastScrollY && currentScrollY > 100) {
      navbar.style.transform = 'translateY(-100%)';
    } else {
      navbar.style.transform = 'translateY(0)';
    }
    lastScrollY = currentScrollY;
    navbar.classList.toggle('scrolled', window.scrollY > 40);
  });
}

// ── Hamburger menu ──
const hamburger = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');
if (hamburger && mobileMenu) {
  hamburger.addEventListener('click', () => {
    mobileMenu.classList.toggle('open');
  });
}

// ── Active nav link ──
const currentPage = window.location.pathname.split('/').pop() || 'index.html';
document.querySelectorAll('.nav-links a, .mobile-menu a').forEach(link => {
  const href = link.getAttribute('href');
  if (href === currentPage || (currentPage === '' && href === 'index.html')) {
    link.classList.add('active');
  } else {
    link.classList.remove('active');
  }
});

// ── Animate numbers on scroll ──
function animateNumbers() {
  const nums = document.querySelectorAll('.num');
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = parseFloat(el.textContent.replace('+', ''));
        const hasPlus = el.textContent.includes('+');
        let current = 0;
        const step = target / 40;
        const interval = setInterval(() => {
          current = Math.min(current + step, target);
          el.textContent = Math.round(current) + (hasPlus ? '+' : '');
          if (current >= target) clearInterval(interval);
        }, 30);
        observer.unobserve(el);
      }
    });
  }, { threshold: 0.5 });
  nums.forEach(n => observer.observe(n));
}
animateNumbers();

// ── Fade-in on scroll ──
const fadeEls = document.querySelectorAll('.service-card, .event-item, .price-card, .team-card, .value-card, .facility-text');
const fadeObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = '1';
      entry.target.style.transform = 'translateY(0)';
      fadeObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });

fadeEls.forEach((el, i) => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(20px)';
  el.style.transition = `opacity 0.5s ease ${i * 0.06}s, transform 0.5s ease ${i * 0.06}s`;
  fadeObserver.observe(el);
});

// ── Hero scroll animation ──
const heroImg = document.querySelector('.hero-media img');
const heroSection = document.querySelector('.hero');
if (heroImg && heroSection) {
  window.addEventListener('scroll', () => {
    const rect = heroSection.getBoundingClientRect();
    const offset = Math.max(-40, Math.min(-rect.top * 0.14, 0));
    heroImg.style.transform = `translateY(${offset}px)`;
  });
}

// ── Contact form ──
const form = document.getElementById('contactForm');
if (form) {
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    btn.textContent = 'Messaggio inviato ✓';
    btn.style.background = '#2D5016';
    btn.disabled = true;
  });
}
