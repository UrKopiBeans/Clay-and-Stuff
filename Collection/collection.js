const cards = document.querySelectorAll('.card');
  const blocks = document.querySelectorAll('.collection-block');
  const searchInput = document.getElementById('searchInput');
  const emptyState = document.getElementById('emptyState');

  // Category filter dropdown elements
  const filterDropdown = document.getElementById('filterDropdown');
  const filterButton = document.getElementById('filterButton');
  const filterMenuButtons = document.querySelectorAll('#filterMenu button');

  // Sort dropdown elements
  const sortDropdown = document.getElementById('sortDropdown');
  const sortButton = document.getElementById('sortButton');
  const sortMenuButtons = document.querySelectorAll('#sortMenu button');

  let activeFilter = 'all';
  let activeSort = 'random';

  function setActive(group, clicked) {
    group.forEach(btn => btn.classList.remove('active'));
    clicked.classList.add('active');
  }

  // open/close toggle, same pattern for filter and sort dropdown
  function setupDropdown(dropdownEl) {
    if (!dropdownEl) {
      return;
    }

    const button = dropdownEl.querySelector('.sort-button');

    button.addEventListener('click', (e) => {
      e.stopPropagation();

      document.querySelectorAll('.sort-dropdown.open').forEach(openDropdown => {
        if (openDropdown !== dropdownEl) {
          openDropdown.classList.remove('open');
        }
      });

      dropdownEl.classList.toggle('open');
    });
  }

  setupDropdown(filterDropdown);
  setupDropdown(sortDropdown);

  // close dropdown when clicking outside it
  document.addEventListener('click', (e) => {
    document.querySelectorAll('.sort-dropdown.open').forEach(dropdownEl => {
      if (!dropdownEl.contains(e.target)) {
        dropdownEl.classList.remove('open');
      }
    });
  });

  // size is always the last meta-chip, e.g. `3.5"` -> 3.5
  function getCardSize(card) {
    const chips = card.querySelectorAll('.meta-chip');
    const sizeChip = chips[chips.length - 1];
    if (!sizeChip) return 0;
    const match = sizeChip.textContent.match(/([\d.]+)/);
    return match ? parseFloat(match[1]) : 0;
  }

  // Get a card's price number (e.g. "Base Price: ₱600" -> 600)
  function getCardPrice(card) {
    const priceEl = card.querySelector('.price');
    if (!priceEl) return 0;
    const match = priceEl.textContent.match(/([\d,]+)/);
    return match ? parseFloat(match[1].replace(/,/g, '')) : 0;
  }

  // Sort the cards inside each collection block
  function applySort() {
    blocks.forEach(block => {
      const grid = block.querySelector('.card-grid');
      const cardsInBlock = [...grid.querySelectorAll('.card')];

      cardsInBlock.sort((a, b) => {
        switch (activeSort) {
          case 'size-asc':
            return getCardSize(a) - getCardSize(b);
          case 'size-desc':
            return getCardSize(b) - getCardSize(a);
          case 'price-asc':
            return getCardPrice(a) - getCardPrice(b);
          case 'price-desc':
            return getCardPrice(b) - getCardPrice(a);
          case 'random':
          default:
            return Math.random() - 0.5;
        }
      });

      cardsInBlock.forEach(card => grid.appendChild(card));
    });
  }

  // filter menu click, also updates the button label
  filterMenuButtons.forEach(btn => btn.addEventListener('click', () => {
    setActive(filterMenuButtons, btn);
    activeFilter = btn.dataset.filter;
    if (filterButton) {
      filterButton.textContent = btn.textContent.trim();
    }
    filterDropdown.classList.remove('open');
    applyFilters();
  }));

  // Handle sort menu clicks
  sortMenuButtons.forEach(btn => btn.addEventListener('click', () => {
    setActive(sortMenuButtons, btn);
    activeSort = btn.dataset.sort;
    if (sortButton) {
      sortButton.textContent = btn.textContent.trim();
    }
    sortDropdown.classList.remove('open');
    applySort();
    applyFilters();
  }));

  function applyFilters() {
    const term = searchInput.value.trim().toLowerCase();
    let anyVisible = false;

    cards.forEach(card => {
      const tags = card.dataset.filter.split(' ');
      const matchesFilter = activeFilter === 'all' || tags.includes(activeFilter);
      const name = card.querySelector('h3').textContent.toLowerCase();
      const matchesSearch = name.includes(term);
      const visible = matchesFilter && matchesSearch;
      card.style.display = visible ? '' : 'none';
      if (visible) anyVisible = true;
    });

    blocks.forEach(block => {
      const hasVisible = [...block.querySelectorAll('.card')]
        .some(c => c.style.display !== 'none');
      block.style.display = hasVisible ? '' : 'none';
    });

    emptyState.hidden = anyVisible;
  }

  searchInput.addEventListener('input', applyFilters);
