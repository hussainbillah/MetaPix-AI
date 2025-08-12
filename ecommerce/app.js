'use strict';

const state = {
  products: [],
  filteredProductIds: [],
  categories: new Set(),
  cartItems: [], // [{productId, quantity}]
};

const elements = {
  searchInputs: [],
  categorySelects: [],
  resultsMeta: document.getElementById('resultsMeta'),
  productsGrid: document.getElementById('productsGrid'),
  productCardTemplate: document.getElementById('productCardTemplate'),
  productDialog: document.getElementById('productDialog'),
  dialogClose: document.getElementById('dialogClose'),
  dialogImage: document.getElementById('dialogImage'),
  dialogTitle: document.getElementById('dialogTitle'),
  dialogCategory: document.getElementById('dialogCategory'),
  dialogPrice: document.getElementById('dialogPrice'),
  dialogDescription: document.getElementById('dialogDescription'),
  dialogAdd: document.getElementById('dialogAdd'),
  cartButton: document.getElementById('cartButton'),
  cartCount: document.getElementById('cartCount'),
  cartDrawer: document.getElementById('cartDrawer'),
  cartClose: document.getElementById('cartClose'),
  cartItems: document.getElementById('cartItems'),
  subtotal: document.getElementById('subtotal'),
  shipping: document.getElementById('shipping'),
  total: document.getElementById('total'),
  checkoutButton: document.getElementById('checkoutButton'),
  checkoutDialog: document.getElementById('checkoutDialog'),
  checkoutClose: document.getElementById('checkoutClose'),
  checkoutForm: document.getElementById('checkoutForm'),
  checkoutStatus: document.getElementById('checkoutStatus'),
};

document.getElementById('year').textContent = new Date().getFullYear();

function formatMoney(value) {
  return `$${value.toFixed(2)}`;
}

function loadCartFromStorage() {
  try {
    const raw = localStorage.getItem('shoplite_cart');
    state.cartItems = raw ? JSON.parse(raw) : [];
  } catch (e) {
    state.cartItems = [];
  }
}

function persistCart() {
  localStorage.setItem('shoplite_cart', JSON.stringify(state.cartItems));
}

function getProductById(id) {
  return state.products.find((p) => p.id === id);
}

function getCartItem(productId) {
  return state.cartItems.find((ci) => ci.productId === productId);
}

function addToCart(productId, quantity = 1) {
  const existing = getCartItem(productId);
  if (existing) {
    existing.quantity += quantity;
  } else {
    state.cartItems.push({ productId, quantity });
  }
  persistCart();
  renderCart();
}

function removeFromCart(productId) {
  state.cartItems = state.cartItems.filter((ci) => ci.productId !== productId);
  persistCart();
  renderCart();
}

function updateQuantity(productId, quantity) {
  const item = getCartItem(productId);
  if (!item) return;
  item.quantity = Math.max(1, quantity);
  persistCart();
  renderCart();
}

function computeCartTotals() {
  const subtotal = state.cartItems.reduce((sum, ci) => {
    const product = getProductById(ci.productId);
    if (!product) return sum;
    return sum + product.price * ci.quantity;
  }, 0);
  const shipping = subtotal > 100 ? 0 : subtotal === 0 ? 0 : 6.99;
  const total = subtotal + shipping;
  return { subtotal, shipping, total };
}

function renderCart() {
  // Count
  const count = state.cartItems.reduce((sum, ci) => sum + ci.quantity, 0);
  elements.cartCount.textContent = String(count);

  // Items
  elements.cartItems.innerHTML = '';
  for (const ci of state.cartItems) {
    const product = getProductById(ci.productId);
    if (!product) continue;

    const row = document.createElement('div');
    row.className = 'flex items-center gap-3 p-3';

    const img = document.createElement('img');
    img.src = product.image;
    img.alt = product.title;
    img.className = 'h-16 w-16 rounded object-cover';

    const content = document.createElement('div');
    content.className = 'flex-1';

    const title = document.createElement('div');
    title.className = 'font-medium';
    title.textContent = product.title;

    const price = document.createElement('div');
    price.className = 'text-sm text-gray-500';
    price.textContent = formatMoney(product.price);

    const qtyRow = document.createElement('div');
    qtyRow.className = 'mt-1 flex items-center gap-2';

    const minus = document.createElement('button');
    minus.className = 'rounded border px-2 py-1';
    minus.textContent = '-';
    minus.addEventListener('click', () => updateQuantity(ci.productId, ci.quantity - 1));

    const qty = document.createElement('input');
    qty.type = 'number';
    qty.min = '1';
    qty.value = String(ci.quantity);
    qty.className = 'w-16 rounded border-gray-300 focus:border-brand focus:ring-brand';
    qty.addEventListener('change', () => updateQuantity(ci.productId, Number(qty.value)));

    const plus = document.createElement('button');
    plus.className = 'rounded border px-2 py-1';
    plus.textContent = '+';
    plus.addEventListener('click', () => updateQuantity(ci.productId, ci.quantity + 1));

    const removeBtn = document.createElement('button');
    removeBtn.className = 'ml-auto rounded-md text-red-600 hover:bg-red-50 px-2 py-1';
    removeBtn.textContent = 'Remove';
    removeBtn.addEventListener('click', () => removeFromCart(ci.productId));

    qtyRow.append(minus, qty, plus, removeBtn);
    content.append(title, price, qtyRow);

    row.append(img, content);
    elements.cartItems.append(row);
  }

  // Totals
  const totals = computeCartTotals();
  elements.subtotal.textContent = formatMoney(totals.subtotal);
  elements.shipping.textContent = formatMoney(totals.shipping);
  elements.total.textContent = formatMoney(totals.total);
}

function setDrawer(open) {
  elements.cartDrawer.style.transform = open ? 'translateX(0)' : 'translateX(100%)';
}

function openProductDialog(product) {
  elements.dialogImage.src = product.image;
  elements.dialogImage.alt = product.title;
  elements.dialogTitle.textContent = product.title;
  elements.dialogCategory.textContent = product.category;
  elements.dialogPrice.textContent = formatMoney(product.price);
  elements.dialogDescription.textContent = product.description;
  elements.dialogAdd.onclick = () => addToCart(product.id, 1);
  elements.productDialog.showModal();
}

function filterProducts(searchTerm, category) {
  const s = (searchTerm || '').toLowerCase().trim();
  state.filteredProductIds = state.products
    .filter((p) => (category === 'all' || p.category === category))
    .filter((p) => {
      if (!s) return true;
      return (
        p.title.toLowerCase().includes(s) ||
        p.description.toLowerCase().includes(s) ||
        p.category.toLowerCase().includes(s)
      );
    })
    .map((p) => p.id);
}

function renderProducts() {
  elements.productsGrid.innerHTML = '';
  const ids = state.filteredProductIds.length ? state.filteredProductIds : state.products.map((p) => p.id);
  const list = ids.map((id) => state.products.find((p) => p.id === id)).filter(Boolean);

  elements.resultsMeta.textContent = `${list.length} product${list.length === 1 ? '' : 's'} found`;

  for (const product of list) {
    const tpl = elements.productCardTemplate.content.cloneNode(true);
    const img = tpl.querySelector('img');
    const title = tpl.querySelector('h3');
    const cat = tpl.querySelector('p');
    const price = tpl.querySelector('span.text-lg');
    const addBtn = tpl.querySelector('button.addToCart');

    img.src = product.image;
    img.alt = product.title;
    title.textContent = product.title;
    cat.textContent = product.category;
    price.textContent = formatMoney(product.price);

    img.addEventListener('click', () => openProductDialog(product));
    title.addEventListener('click', () => openProductDialog(product));

    addBtn.addEventListener('click', () => addToCart(product.id));

    elements.productsGrid.append(tpl);
  }
}

function initFilters() {
  const search = document.getElementById('searchInput');
  const searchMobile = document.getElementById('searchInputMobile');
  const category = document.getElementById('categorySelect');
  const categoryMobile = document.getElementById('categorySelectMobile');

  elements.searchInputs = [search, searchMobile].filter(Boolean);
  elements.categorySelects = [category, categoryMobile].filter(Boolean);

  const onChange = () => {
    const s = (elements.searchInputs[0]?.value || elements.searchInputs[1]?.value || '').trim();
    const c = elements.categorySelects[0]?.value || 'all';
    filterProducts(s, c);
    renderProducts();
  };

  for (const input of elements.searchInputs) input.addEventListener('input', onChange);
  for (const sel of elements.categorySelects) sel.addEventListener('change', onChange);
}

function populateCategories() {
  const options = ['all', ...Array.from(state.categories)];
  for (const sel of elements.categorySelects) {
    sel.innerHTML = '';
    for (const c of options) {
      const opt = document.createElement('option');
      opt.value = c;
      opt.textContent = c === 'all' ? 'All Categories' : c;
      sel.append(opt);
    }
  }
}

function setupCartEvents() {
  elements.cartButton.addEventListener('click', () => setDrawer(true));
  elements.cartClose.addEventListener('click', () => setDrawer(false));
  elements.checkoutButton.addEventListener('click', () => {
    if (state.cartItems.length === 0) return alert('Your cart is empty');
    elements.checkoutDialog.showModal();
  });
  elements.checkoutClose.addEventListener('click', () => elements.checkoutDialog.close());

  elements.checkoutForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    elements.checkoutStatus.textContent = 'Processing payment...';
    await new Promise((r) => setTimeout(r, 1000));
    const orderId = `ORD-${Math.random().toString(36).slice(2, 8).toUpperCase()}`;
    elements.checkoutStatus.textContent = `Success! Your order ${orderId} has been placed.`;
    state.cartItems = [];
    persistCart();
    renderCart();
    setTimeout(() => elements.checkoutDialog.close(), 1200);
  });
}

async function loadProducts() {
  try {
    const res = await fetch('./products.json');
    if (!res.ok) throw new Error('Network error');
    const data = await res.json();
    state.products = data.products;
    state.categories = new Set(state.products.map((p) => p.category));
  } catch (err) {
    // Fallback inline data when running from file://
    state.products = [
      { id: 1, title: 'Classic Cotton T-Shirt', description: 'Soft, breathable cotton tee for everyday comfort.', price: 19.99, category: 'Apparel', image: 'https://images.unsplash.com/photo-1520975922284-7b683b82d46f?w=800&q=80' },
      { id: 2, title: 'Slim Fit Jeans', description: 'Stretch denim with a modern slim silhouette.', price: 49.99, category: 'Apparel', image: 'https://images.unsplash.com/photo-1490114538077-0a7f8cb49891?w=800&q=80' },
      { id: 3, title: 'Wireless Headphones', description: 'Over-ear Bluetooth headphones with noise isolation.', price: 89.0, category: 'Electronics', image: 'https://images.unsplash.com/photo-1518444152891-d55c5bc75f19?w=800&q=80' }
    ];
    state.categories = new Set(state.products.map((p) => p.category));
  }
}

async function bootstrap() {
  loadCartFromStorage();
  initFilters();
  setupCartEvents();

  await loadProducts();
  populateCategories();
  filterProducts('', 'all');
  renderProducts();
  renderCart();
}

// Dialog events
if (elements.dialogClose) elements.dialogClose.addEventListener('click', () => elements.productDialog.close());

bootstrap();