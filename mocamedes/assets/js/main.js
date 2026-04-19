/* =====================================================
   PORTAL DO MOÇAMEDENSE — Global JS
   ===================================================== */

// =================== USER DROPDOWN ===================
function toggleUserDropdown() {
  const dd = document.getElementById('userDropdown');
  if (dd) dd.classList.toggle('hidden');
}

window.addEventListener('click', function(e) {
  if (!e.target.closest('.user-menu')) {
    document.querySelectorAll('.user-dropdown').forEach(d => d.classList.add('hidden'));
  }
});

// =================== MOBILE NAV ===================
document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.querySelector('.mobile-toggle');
  const mobileNav = document.querySelector('.mobile-nav');
  if (toggle && mobileNav) {
    toggle.addEventListener('click', function () {
      mobileNav.classList.toggle('open');
      mobileNav.classList.toggle('hidden');
    });
  }

  // =================== SCROLL ANIMATIONS ===================
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
      }
    });
  }, { threshold: 0.12 });

  document.querySelectorAll('.animate-on-scroll').forEach(el => observer.observe(el));

  // =================== COUNTER ANIMATION ===================
  function animateCounter(el, target, suffix = '') {
    const duration = 2000;
    const startTime = performance.now();
    const isDecimal = String(target).includes('.');

    function update(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = eased * target;
      el.textContent = isDecimal
        ? current.toFixed(1) + suffix
        : Math.floor(current).toLocaleString('pt-PT') + suffix;
      if (progress < 1) requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
  }

  const statsObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const raw = el.dataset.count;
        const suffix = el.dataset.suffix || '';
        if (raw) animateCounter(el, parseFloat(raw), suffix);
        statsObserver.unobserve(el);
      }
    });
  }, { threshold: 0.5 });

  document.querySelectorAll('[data-count]').forEach(el => statsObserver.observe(el));

  // =================== HERO STARS ===================
  const starsContainer = document.getElementById('heroStars');
  if (starsContainer) {
    for (let i = 0; i < 80; i++) {
      const star = document.createElement('div');
      star.className = 'star';
      star.style.left = Math.random() * 100 + '%';
      star.style.top  = Math.random() * 100 + '%';
      star.style.setProperty('--dur',   (2 + Math.random() * 4) + 's');
      star.style.setProperty('--delay', (Math.random() * 4) + 's');
      star.style.width  = (Math.random() > 0.7 ? 3 : 2) + 'px';
      star.style.height = star.style.width;
      star.style.opacity = (0.2 + Math.random() * 0.5).toString();
      starsContainer.appendChild(star);
    }
  }

  // =================== HERO CANVAS ANIMATION ===================
  const canvas = document.getElementById('heroCanvas');
  if (canvas) {
    const ctx = canvas.getContext('2d');
    let W, H, particles = [];
    const PARTICLE_COUNT = 60;

    function resize() {
      W = canvas.width  = canvas.offsetWidth;
      H = canvas.height = canvas.offsetHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    class Particle {
      constructor() { this.reset(); }
      reset() {
        this.x = Math.random() * W;
        this.y = Math.random() * H;
        this.vx = (Math.random() - 0.5) * 0.4;
        this.vy = (Math.random() - 0.5) * 0.4;
        this.r  = Math.random() * 2 + 0.5;
        this.alpha = Math.random() * 0.4 + 0.1;
        this.color = Math.random() > 0.6
          ? `rgba(201,146,42,${this.alpha})`
          : `rgba(45,140,240,${this.alpha})`;
      }
      update() {
        this.x += this.vx;
        this.y += this.vy;
        if (this.x < 0 || this.x > W || this.y < 0 || this.y > H) this.reset();
      }
      draw() {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.r, 0, Math.PI * 2);
        ctx.fillStyle = this.color;
        ctx.fill();
      }
    }

    for (let i = 0; i < PARTICLE_COUNT; i++) particles.push(new Particle());

    function drawConnections() {
      for (let i = 0; i < particles.length; i++) {
        for (let j = i + 1; j < particles.length; j++) {
          const dx = particles[i].x - particles[j].x;
          const dy = particles[i].y - particles[j].y;
          const dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < 120) {
            ctx.beginPath();
            ctx.strokeStyle = `rgba(45,140,240,${0.08 * (1 - dist / 120)})`;
            ctx.lineWidth = 1;
            ctx.moveTo(particles[i].x, particles[i].y);
            ctx.lineTo(particles[j].x, particles[j].y);
            ctx.stroke();
          }
        }
      }
    }

    function animate() {
      ctx.clearRect(0, 0, W, H);
      drawConnections();
      particles.forEach(p => { p.update(); p.draw(); });
      requestAnimationFrame(animate);
    }
    animate();
  }

  // =================== SMOOTH SCROLL ===================
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // =================== HEADER SHADOW ON SCROLL ===================
  const header = document.querySelector('.header');
  if (header) {
    window.addEventListener('scroll', function() {
      header.style.boxShadow = window.scrollY > 20
        ? '0 4px 30px rgba(10,37,64,0.12)'
        : '0 1px 0 rgba(10,37,64,0.06), 0 4px 16px rgba(10,37,64,0.04)';
    });
  }

  // =================== TABS ===================
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const tabGroup = this.closest('[data-tabs]') || this.closest('.tabs-container');
      if (!tabGroup) {
        // Global tabs
        const target = this.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        const panel = document.getElementById(target);
        if (panel) panel.classList.add('active');
      }
    });
  });
});

// =================== TOAST NOTIFICATION ===================
function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.style.cssText = `
    position:fixed; bottom:2rem; right:2rem; z-index:9999;
    background:${type === 'success' ? '#0d6e45' : type === 'error' ? '#c0392b' : '#1a5fa8'};
    color:white; padding:0.9rem 1.4rem; border-radius:0.75rem;
    font-size:0.875rem; font-weight:500; box-shadow:0 8px 24px rgba(0,0,0,0.2);
    transform:translateY(100px); transition:transform 0.4s cubic-bezier(0.16,1,0.3,1);
    display:flex; align-items:center; gap:0.6rem; max-width:360px;
    font-family:'DM Sans',sans-serif;
  `;
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => { toast.style.transform = 'translateY(0)'; }, 10);
  setTimeout(() => {
    toast.style.transform = 'translateY(100px)';
    setTimeout(() => toast.remove(), 400);
  }, 3500);
}
