/* Susu Connect v3 — app.js */

// ── Sidebar toggle ──
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('overlay');
const hamburger = document.getElementById('hamburger');

function openSB()  { sidebar?.classList.add('open');    overlay?.classList.add('active');    document.body.style.overflow='hidden'; }
function closeSB() { sidebar?.classList.remove('open'); overlay?.classList.remove('active'); document.body.style.overflow=''; }

hamburger?.addEventListener('click', openSB);
overlay?.addEventListener('click', closeSB);
window.addEventListener('resize', () => { if(window.innerWidth > 768) closeSB(); });

// ── Alert dismiss ──
document.querySelectorAll('.alert-close').forEach(btn => {
  btn.addEventListener('click', () => {
    const el = btn.closest('.alert');
    el.style.transition = 'opacity .2s, transform .2s';
    el.style.opacity = '0'; el.style.transform = 'translateY(-4px)';
    setTimeout(() => el.remove(), 200);
  });
});
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity .3s'; el.style.opacity = '0';
    setTimeout(() => el.remove(), 300);
  }, 6000);
});

// ── Button loading state ──
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    if (btn && !btn.dataset.noLoad) {
      btn.classList.add('loading');
      btn.disabled = true;
    }
  });
});

// ── Modal system ──
window.openModal = function(id) {
  document.getElementById(id)?.classList.add('active');
  document.body.style.overflow = 'hidden';
};
window.closeModal = function(id) {
  document.getElementById(id)?.classList.remove('active');
  document.body.style.overflow = '';
};
document.querySelectorAll('.modal-backdrop').forEach(m => {
  m.addEventListener('click', function(e) {
    if (e.target === this) { this.classList.remove('active'); document.body.style.overflow=''; }
  });
});

// ── Confirm dialogs ──
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm)) e.preventDefault();
  });
});

// ── Search filter ──
const searchInput = document.getElementById('table-search');
if (searchInput) {
  searchInput.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('[data-searchable]').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}
