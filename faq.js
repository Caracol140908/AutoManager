(function () {
  document.querySelectorAll('#navbar a[href^="#"]').forEach(function (a) {
    a.setAttribute('href', 'index.php' + a.getAttribute('href'));
  });

  var items = Array.prototype.slice.call(document.querySelectorAll('[data-faq-item]'));
  var searchInput = document.getElementById('faqSearch');
  var searchClear = document.getElementById('faqSearchClear');
  var searchCount = document.getElementById('faqSearchCount');
  var emptyEl = document.getElementById('faqEmpty');
  var navLinks = Array.prototype.slice.call(document.querySelectorAll('[data-faq-nav]'));
  var sections = Array.prototype.slice.call(document.querySelectorAll('[data-faq-section]'));
  var btnExpand = document.getElementById('faqExpandAll');
  var btnCollapse = document.getElementById('faqCollapseAll');

  function closeItem(item) {
    item.classList.remove('is-open');
    var btn = item.querySelector('.faq-question');
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }

  function openItem(item) {
    item.classList.add('is-open');
    var btn = item.querySelector('.faq-question');
    if (btn) btn.setAttribute('aria-expanded', 'true');
  }

  function visibleItems() {
    return items.filter(function (item) {
      return !item.classList.contains('is-hidden');
    });
  }

  items.forEach(function (item) {
    var btn = item.querySelector('.faq-question');
    if (!btn) return;

    var answerId = 'faq-ans-' + item.getAttribute('data-faq-id');
    var answer = item.querySelector('.faq-answer');
    if (answer && answerId) {
      answer.id = answerId;
      btn.setAttribute('aria-controls', answerId);
    }

    btn.addEventListener('click', function () {
      var isOpen = item.classList.contains('is-open');
      if (isOpen) {
        closeItem(item);
      } else {
        items.forEach(closeItem);
        openItem(item);
      }
    });

    btn.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        btn.click();
      }
    });
  });

  if (btnExpand) {
    btnExpand.addEventListener('click', function () {
      visibleItems().forEach(openItem);
    });
  }

  if (btnCollapse) {
    btnCollapse.addEventListener('click', function () {
      items.forEach(closeItem);
    });
  }

  function applySearch() {
    var q = (searchInput && searchInput.value || '').trim().toLowerCase();
    var visible = 0;

    items.forEach(function (item) {
      var blob = item.getAttribute('data-search') || '';
      var show = q === '' || blob.indexOf(q) !== -1;
      item.classList.toggle('is-hidden', !show);
      if (!show) closeItem(item);
      if (show) visible++;
    });

    sections.forEach(function (section) {
      var any = section.querySelector('[data-faq-item]:not(.is-hidden)');
      section.hidden = !any;
    });

    if (emptyEl) emptyEl.classList.toggle('is-visible', visible === 0 && q !== '');
    if (searchClear) searchClear.hidden = q === '';

    if (searchCount) {
      if (q === '') {
        searchCount.textContent = '';
      } else {
        searchCount.textContent = visible === 1 ? '1 resultado' : visible + ' resultados';
      }
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', applySearch);
    searchInput.addEventListener('search', applySearch);
  }

  if (searchClear) {
    searchClear.addEventListener('click', function () {
      if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
      }
      applySearch();
    });
  }

  emptyEl && emptyEl.querySelector('a') && emptyEl.querySelector('a').addEventListener('click', function (e) {
    e.preventDefault();
    if (searchInput) {
      searchInput.value = '';
      searchInput.focus();
    }
    applySearch();
  });

  function setActiveNav(id) {
    navLinks.forEach(function (link) {
      link.classList.toggle('is-active', link.getAttribute('data-faq-nav') === id);
    });
  }

  navLinks.forEach(function (link) {
    link.addEventListener('click', function (e) {
      var id = link.getAttribute('data-faq-nav');
      setActiveNav(id);
    });
  });

  if (navLinks.length && sections.length && 'IntersectionObserver' in window) {
    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            setActiveNav(entry.target.id.replace('faq-', ''));
          }
        });
      },
      { rootMargin: '-20% 0px -65% 0px', threshold: 0 }
    );
    sections.forEach(function (section) {
      observer.observe(section);
    });
  }

  var hash = window.location.hash.replace('#', '');
  if (hash.indexOf('faq-') === 0) {
    var el = document.getElementById(hash);
    if (el) {
      setTimeout(function () {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setActiveNav(hash.replace('faq-', ''));
      }, 100);
    }
  }
})();
