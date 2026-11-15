(() => {
  'use strict';

  const API_BASE = (window.WPCatalogConfig?.apiBase || `${window.location.origin}/wp-json/wpcatalog/v1`).replace(/\/$/, '');
  const LOCALE = window.WPCatalogConfig?.locale || 'fr-MA';
  const CART_KEY = 'nova-boutique-cart-v1';
  const state = {
    page: 1,
    perPage: 9,
    totalPages: 0,
    debounce: null,
    store: { name: 'NOVA Boutique', currency: 'MAD', shipping_fee: 35, free_shipping_threshold: 600, payment_methods: { cod: true, bank_transfer: true } },
    products: new Map(),
    cart: readCart(),
  };

  const $ = (selector) => document.querySelector(selector);
  const elements = {
    status: $('#api-status'),
    form: $('#filter-form'),
    search: $('#search'),
    category: $('#category'),
    brand: $('#brand'),
    minPrice: $('#min-price'),
    maxPrice: $('#max-price'),
    stock: $('#stock'),
    onSale: $('#on-sale'),
    attributeKey: $('#attribute-key'),
    attributeValue: $('#attribute-value'),
    orderby: $('#orderby'),
    grid: $('#product-grid'),
    count: $('#result-count'),
    pagination: $('#pagination'),
    empty: $('#empty-state'),
    error: $('#error-message'),
    detailModal: $('#detail-modal'),
    detailContent: $('#detail-content'),
    cartOverlay: $('#cart-overlay'),
    cartLines: $('#cart-lines'),
    cartEmpty: $('#cart-empty'),
    cartCheckout: $('#cart-checkout'),
    checkoutForm: $('#checkout-form'),
    checkoutError: $('#checkout-error'),
    paymentMethod: $('#payment-method'),
    paymentHint: $('#payment-hint'),
    trackingForm: $('#tracking-form'),
    trackingResult: $('#tracking-result'),
  };

  function readCart() {
    try {
      const saved = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
      return Array.isArray(saved) ? saved.filter((item) => Number.isInteger(Number(item.id)) && Number(item.id) > 0 && Number(item.quantity) > 0) : [];
    } catch (_) {
      return [];
    }
  }

  function persistCart() {
    localStorage.setItem(CART_KEY, JSON.stringify(state.cart));
  }

  function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  }

  function formatPrice(price, currency = state.store.currency) {
    const amount = Number(price || 0);
    try {
      return new Intl.NumberFormat(LOCALE, { style: 'currency', currency: currency || 'MAD', maximumFractionDigits: 2 }).format(amount);
    } catch (_) {
      return `${amount.toFixed(2)} ${currency || ''}`.trim();
    }
  }

  async function api(path, options = {}) {
    const response = await fetch(`${API_BASE}${path}`, {
      method: options.method || 'GET',
      headers: {
        Accept: 'application/json',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(options.headers || {}),
      },
      body: options.body ? JSON.stringify(options.body) : undefined,
      credentials: 'same-origin',
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(payload.message || payload.code || `Erreur de requête (${response.status})`);
    }
    return payload;
  }

  function setError(message = '') {
    elements.error.hidden = !message;
    elements.error.textContent = message;
  }

  function setCheckoutError(message = '') {
    elements.checkoutError.hidden = !message;
    elements.checkoutError.textContent = message;
  }

  async function loadStore() {
    const payload = await api('/store');
    state.store = { ...state.store, ...(payload.data || {}) };
    const name = state.store.name || 'NOVA Boutique';
    $('#store-name').textContent = name.replace(/\s+(Boutique)$/i, '').toUpperCase();
    $('#footer-store-name').textContent = name.replace(/\s+(Boutique)$/i, '').toUpperCase();
    $('#copyright-store').textContent = name;
    if (state.store.contact_email) {
      $('#contact-email').textContent = state.store.contact_email;
      $('#contact-email').href = `mailto:${state.store.contact_email}`;
    }
    renderPaymentOptions();
    renderCart();
  }

  async function loadCategories() {
    const payload = await api('/categories');
    (payload.data || []).forEach((category) => {
      const option = document.createElement('option');
      option.value = category.slug;
      option.textContent = `${category.name} (${category.count})`;
      elements.category.append(option);
    });
  }

  function renderPaymentOptions() {
    const methods = state.store.payment_methods || {};
    elements.paymentMethod.innerHTML = '';
    if (methods.cod) {
      const option = document.createElement('option');
      option.value = 'cod';
      option.textContent = 'Paiement à la livraison';
      elements.paymentMethod.append(option);
    }
    if (methods.bank_transfer) {
      const option = document.createElement('option');
      option.value = 'bank_transfer';
      option.textContent = 'Virement bancaire';
      elements.paymentMethod.append(option);
    }
    if (!elements.paymentMethod.options.length) {
      const option = document.createElement('option');
      option.value = '';
      option.textContent = 'Aucun moyen de paiement configuré';
      option.disabled = true;
      elements.paymentMethod.append(option);
    }
    updatePaymentHint();
  }

  function updatePaymentHint() {
    if (elements.paymentMethod.value === 'bank_transfer') {
      elements.paymentHint.textContent = state.store.bank_transfer_instructions || 'Les instructions de virement seront communiquées après validation.';
      elements.paymentHint.hidden = false;
    } else {
      elements.paymentHint.textContent = 'Le règlement sera effectué lors de la réception de votre commande.';
      elements.paymentHint.hidden = false;
    }
  }

  function queryString() {
    const params = new URLSearchParams({ page: String(state.page), per_page: String(state.perPage), orderby: elements.orderby.value });
    const fields = [
      ['q', elements.search.value.trim()],
      ['category', elements.category.value],
      ['brand', elements.brand.value.trim()],
      ['min_price', elements.minPrice.value],
      ['max_price', elements.maxPrice.value],
      ['stock', elements.stock.value],
      ['on_sale', elements.onSale.checked ? '1' : ''],
    ];
    fields.forEach(([key, value]) => { if (value !== '') params.set(key, value); });
    const key = elements.attributeKey.value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
    const value = elements.attributeValue.value.trim();
    if (key && value) params.set(`attr[${key}]`, value);
    return params.toString();
  }

  function stockLabel(product) {
    if (product.stock_status === 'outofstock') return 'Épuisé';
    if (product.stock_status === 'backorder') return 'Sur commande';
    if (product.manage_stock && Number(product.stock_quantity) <= 5) return `Plus que ${Number(product.stock_quantity)} en stock`;
    return 'En stock';
  }

  function productCard(product) {
    const category = product.categories?.[0]?.name || 'Sélection NOVA';
    const image = product.image
      ? `<img src="${escapeHTML(product.image)}" alt="${escapeHTML(product.title)}" loading="lazy">`
      : '<span class="image-placeholder" aria-hidden="true">✳</span>';
    const out = product.stock_status === 'outofstock';
    const sale = product.on_sale && product.sale_price !== null;
    const badge = sale ? '<span class="product-badge sale-badge">PROMO</span>' : (product.featured ? '<span class="product-badge">SÉLECTION</span>' : '');
    const oldPrice = sale ? `<del>${escapeHTML(formatPrice(product.regular_price, product.currency))}</del>` : '';
    const stockClass = out ? 'outofstock' : product.stock_status === 'backorder' ? 'backorder' : '';
    return `<article class="product-card">
      <div class="product-card-media">
        <button class="product-image-button" type="button" data-view-id="${Number(product.id)}" aria-label="Voir ${escapeHTML(product.title)}">${image}</button>
        ${badge}
        <button class="quick-view" type="button" data-view-id="${Number(product.id)}" aria-label="Voir le produit">↗</button>
      </div>
      <div class="product-info">
        <div class="product-overline"><span>${escapeHTML(product.brand || category)}</span><span class="stock-dot ${stockClass}">${escapeHTML(stockLabel(product))}</span></div>
        <h3><button type="button" data-view-id="${Number(product.id)}">${escapeHTML(product.title)}</button></h3>
        <p class="product-excerpt">${escapeHTML(product.excerpt || 'Découvrez les détails et caractéristiques de ce produit.')}</p>
        <div class="product-bottom"><div class="product-price"><strong>${escapeHTML(formatPrice(product.price, product.currency))}</strong>${oldPrice}</div>
          <button class="add-button" type="button" data-add-id="${Number(product.id)}" ${out ? 'disabled' : ''} aria-label="Ajouter ${escapeHTML(product.title)} au panier">${out ? 'Indisponible' : '<span aria-hidden="true">＋</span> Ajouter'}</button>
        </div>
      </div>
    </article>`;
  }

  function renderPagination(meta) {
    state.totalPages = Number(meta.total_pages || 0);
    elements.pagination.innerHTML = '';
    if (state.totalPages <= 1) return;
    const addButton = (label, page, disabled = false, active = false) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `page-button${active ? ' active' : ''}`;
      button.textContent = label;
      button.disabled = disabled;
      button.addEventListener('click', () => {
        state.page = page;
        loadProducts();
        $('#catalogue').scrollIntoView({ behavior: 'smooth' });
      });
      elements.pagination.append(button);
    };
    addButton('←', state.page - 1, state.page <= 1);
    const start = Math.max(1, state.page - 2);
    const end = Math.min(state.totalPages, state.page + 2);
    for (let page = start; page <= end; page += 1) addButton(String(page), page, false, page === state.page);
    addButton('→', state.page + 1, state.page >= state.totalPages);
  }

  async function loadProducts() {
    setError();
    elements.grid.setAttribute('aria-busy', 'true');
    try {
      const payload = await api(`/products?${queryString()}`);
      const products = payload.data || [];
      products.forEach((product) => state.products.set(Number(product.id), product));
      elements.grid.innerHTML = products.map(productCard).join('');
      const total = Number(payload.meta?.total || 0);
      elements.count.textContent = `${total.toLocaleString(LOCALE)} produit${total > 1 ? 's' : ''} trouvé${total > 1 ? 's' : ''}`;
      elements.empty.hidden = products.length > 0;
      renderPagination(payload.meta || {});
    } catch (error) {
      elements.grid.innerHTML = '';
      elements.count.textContent = 'Catalogue indisponible';
      elements.empty.hidden = true;
      setError(`${error.message}. Vérifiez l’URL de l’API et la configuration WordPress.`);
    } finally {
      elements.grid.removeAttribute('aria-busy');
    }
  }

  async function showProduct(id) {
    try {
      let product = state.products.get(Number(id));
      if (!product) {
        const payload = await api(`/products/${Number(id)}`);
        product = payload.data;
      }
      if (!product) throw new Error('Les détails de ce produit sont indisponibles.');
      state.products.set(Number(id), product);
      const attributes = Object.entries(product.attributes || {}).map(([key, value]) =>
        `<div class="attribute-item"><strong>${escapeHTML(key.replace(/[_-]/g, ' '))}</strong><span>${escapeHTML(value)}</span></div>`).join('');
      const image = product.image
        ? `<img class="detail-image" src="${escapeHTML(product.image)}" alt="${escapeHTML(product.title)}">`
        : '<div class="detail-image image-placeholder">✳</div>';
      const categories = (product.categories || []).map((item) => escapeHTML(item.name)).join(' · ') || 'Sélection NOVA';
      const sale = product.on_sale && product.sale_price !== null;
      const priceMarkup = sale
        ? `<div class="detail-price"><strong>${escapeHTML(formatPrice(product.price, product.currency))}</strong><del>${escapeHTML(formatPrice(product.regular_price, product.currency))}</del><span class="detail-sale-tag">Prix promotionnel</span></div>`
        : `<div class="detail-price"><strong>${escapeHTML(formatPrice(product.price, product.currency))}</strong></div>`;
      const disabled = product.stock_status === 'outofstock';
      const stockText = product.stock_status === 'outofstock' ? 'Actuellement épuisé' : stockLabel(product);
      elements.detailContent.innerHTML = `<div class="detail-layout"><div class="detail-visual">${image}</div><div class="detail-copy">
        <p class="detail-category">${categories}</p><h2 id="detail-title">${escapeHTML(product.title)}</h2>
        <p class="detail-reference">Réf. ${escapeHTML(product.sku || 'Non renseignée')} ${product.brand ? `· ${escapeHTML(product.brand)}` : ''}</p>
        ${priceMarkup}
        <p class="detail-stock">${escapeHTML(stockText)}</p>
        <div class="detail-description">${product.description || escapeHTML(product.excerpt || '')}</div>
        ${attributes ? `<div class="attribute-list"><h3>Caractéristiques</h3>${attributes}</div>` : ''}
        <button class="button button-accent detail-add" type="button" data-add-id="${Number(product.id)}" ${disabled ? 'disabled' : ''}>${disabled ? 'Indisponible' : 'Ajouter au panier'} <span>＋</span></button>
      </div></div>`;
      elements.detailModal.hidden = false;
      document.body.classList.add('modal-open');
    } catch (error) {
      setError(error.message);
      $('#catalogue').scrollIntoView({ behavior: 'smooth' });
    }
  }

  function closeDetail() {
    elements.detailModal.hidden = true;
    if (elements.cartOverlay.hidden && $('#order-success').hidden) document.body.classList.remove('modal-open');
  }

  function addToCart(id) {
    const product = state.products.get(Number(id));
    if (!product) return;
    if (product.stock_status === 'outofstock') return;
    const current = state.cart.find((item) => Number(item.id) === Number(id));
    if (current) {
      const cap = product.manage_stock ? Math.max(1, Number(product.stock_quantity)) : 20;
      if (current.quantity >= Math.min(20, cap)) {
        openCart();
        setCheckoutError(`La quantité disponible pour « ${product.title} » est limitée.`);
        return;
      }
      current.quantity += 1;
      current.product = product;
    } else {
      state.cart.push({ id: Number(id), quantity: 1, product });
    }
    persistCart();
    renderCart();
    openCart();
    closeDetail();
  }

  function cartSubtotal() {
    return state.cart.reduce((sum, item) => sum + Number(item.product?.price || 0) * Number(item.quantity || 0), 0);
  }

  function cartShipping(subtotal) {
    const threshold = Number(state.store.free_shipping_threshold || 0);
    if (threshold > 0 && subtotal >= threshold) return 0;
    return Number(state.store.shipping_fee || 0);
  }

  function renderCart() {
    const count = state.cart.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
    $('#cart-count').textContent = String(count);
    $('#cart-line-count').textContent = `(${count})`;
    elements.cartEmpty.hidden = state.cart.length > 0;
    elements.cartCheckout.hidden = state.cart.length === 0;
    if (!state.cart.length) {
      elements.cartLines.innerHTML = '';
      $('#cart-subtotal').textContent = formatPrice(0);
      $('#cart-shipping').textContent = formatPrice(0);
      $('#cart-total').textContent = formatPrice(0);
      return;
    }
    elements.cartLines.innerHTML = state.cart.map((item) => {
      const product = item.product || {};
      const image = product.image ? `<img src="${escapeHTML(product.image)}" alt="">` : '<span class="cart-placeholder">✳</span>';
      return `<article class="cart-line"><div class="cart-line-image">${image}</div><div class="cart-line-copy"><strong>${escapeHTML(product.title || `Produit #${item.id}`)}</strong><small>${escapeHTML(formatPrice(product.price, product.currency))}</small><div class="quantity-control"><button type="button" data-quantity-id="${Number(item.id)}" data-delta="-1" aria-label="Diminuer la quantité">−</button><span>${Number(item.quantity)}</span><button type="button" data-quantity-id="${Number(item.id)}" data-delta="1" aria-label="Augmenter la quantité">＋</button></div></div><button class="remove-line" type="button" data-remove-id="${Number(item.id)}" aria-label="Retirer du panier">×</button></article>`;
    }).join('');
    const subtotal = cartSubtotal();
    const shipping = cartShipping(subtotal);
    $('#cart-subtotal').textContent = formatPrice(subtotal);
    $('#cart-shipping').textContent = shipping === 0 ? 'Offerte' : formatPrice(shipping);
    $('#cart-total').textContent = formatPrice(subtotal + shipping);
    const threshold = Number(state.store.free_shipping_threshold || 0);
    $('#shipping-hint').textContent = threshold > 0 && subtotal < threshold
      ? `Encore ${formatPrice(threshold - subtotal)} pour bénéficier de la livraison offerte.`
      : (shipping === 0 ? 'La livraison est offerte pour ce panier.' : 'Les frais définitifs sont confirmés à la commande.');
    setCheckoutError();
  }

  function openCart() {
    elements.cartOverlay.hidden = false;
    document.body.classList.add('modal-open');
    renderCart();
  }

  function closeCart() {
    elements.cartOverlay.hidden = true;
    document.body.classList.remove('modal-open');
  }

  function changeQuantity(id, delta) {
    const item = state.cart.find((line) => Number(line.id) === Number(id));
    if (!item) return;
    const max = item.product?.manage_stock ? Math.max(1, Number(item.product.stock_quantity)) : 20;
    const next = Number(item.quantity) + Number(delta);
    if (next < 1) {
      state.cart = state.cart.filter((line) => Number(line.id) !== Number(id));
    } else {
      item.quantity = Math.min(20, max, next);
    }
    persistCart();
    renderCart();
  }

  async function submitCheckout(event) {
    event.preventDefault();
    setCheckoutError();
    if (!state.cart.length) return;
    const submit = $('#checkout-submit');
    submit.disabled = true;
    submit.innerHTML = 'Enregistrement en cours…';
    const form = new FormData(elements.checkoutForm);
    const customer = Object.fromEntries(['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'note'].map((key) => [key, String(form.get(key) || '').trim()]));
    try {
      const payload = await api('/orders/checkout', {
        method: 'POST',
        body: {
          customer,
          payment_method: String(form.get('payment_method') || ''),
          items: state.cart.map((item) => ({ product_id: Number(item.id), quantity: Number(item.quantity) })),
        },
      });
      const order = payload.data || {};
      state.cart = [];
      persistCart();
      renderCart();
      elements.checkoutForm.reset();
      closeCart();
      $('#success-order-number').textContent = order.order_number || '';
      $('#success-total').textContent = formatPrice(order.total, order.currency);
      $('#success-message').textContent = order.message || 'Votre commande a bien été enregistrée. Un récapitulatif vous sera envoyé par e-mail.';
      $('#order-success').hidden = false;
      document.body.classList.add('modal-open');
      state.page = 1;
      await loadProducts();
    } catch (error) {
      setCheckoutError(error.message);
    } finally {
      submit.disabled = false;
      submit.innerHTML = 'Confirmer ma commande <span>→</span>';
    }
  }

  async function trackOrder(event) {
    event.preventDefault();
    const button = elements.trackingForm.querySelector('button[type="submit"]');
    const original = button.innerHTML;
    button.disabled = true;
    button.textContent = 'Recherche…';
    elements.trackingResult.hidden = true;
    try {
      const form = new FormData(elements.trackingForm);
      const payload = await api('/orders/track', { method: 'POST', body: { order_number: form.get('order_number'), email: form.get('email') } });
      const order = payload.data;
      const labels = { en_attente: 'En attente de confirmation', confirmee: 'Commande confirmée', en_preparation: 'En préparation', expediee: 'Commande expédiée', terminee: 'Commande terminée', annulee: 'Commande annulée' };
      const items = (order.items || []).map((item) => `<li>${escapeHTML(item.name)} <span>× ${Number(item.quantity)}</span></li>`).join('');
      elements.trackingResult.innerHTML = `<div class="tracking-success"><span class="tracking-status-dot"></span><div><small>ÉTAT ACTUEL</small><strong>${escapeHTML(labels[order.status] || order.status)}</strong></div></div><p class="tracking-total">Total de la commande : <strong>${escapeHTML(formatPrice(order.total, order.currency))}</strong></p><ul>${items}</ul>`;
      elements.trackingResult.hidden = false;
    } catch (error) {
      elements.trackingResult.innerHTML = `<p class="tracking-error">${escapeHTML(error.message)}</p>`;
      elements.trackingResult.hidden = false;
    } finally {
      button.disabled = false;
      button.innerHTML = original;
    }
  }

  elements.grid.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-add-id]');
    if (addButton) {
      addToCart(addButton.dataset.addId);
      return;
    }
    const viewButton = event.target.closest('[data-view-id]');
    if (viewButton) showProduct(viewButton.dataset.viewId);
  });

  elements.detailContent.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-add-id]');
    if (addButton) addToCart(addButton.dataset.addId);
  });

  elements.cartLines.addEventListener('click', (event) => {
    const quantityButton = event.target.closest('[data-quantity-id]');
    const removeButton = event.target.closest('[data-remove-id]');
    if (quantityButton) changeQuantity(quantityButton.dataset.quantityId, Number(quantityButton.dataset.delta));
    if (removeButton) {
      state.cart = state.cart.filter((item) => Number(item.id) !== Number(removeButton.dataset.removeId));
      persistCart();
      renderCart();
    }
  });

  elements.form.addEventListener('submit', (event) => { event.preventDefault(); state.page = 1; loadProducts(); });
  elements.search.addEventListener('input', () => {
    clearTimeout(state.debounce);
    state.debounce = setTimeout(() => { state.page = 1; loadProducts(); }, 300);
  });
  elements.orderby.addEventListener('change', () => { state.page = 1; loadProducts(); });
  elements.onSale.addEventListener('change', () => { state.page = 1; loadProducts(); });

  function resetFilters() {
    elements.form.reset();
    state.page = 1;
    loadProducts();
  }

  $('#reset-filters').addEventListener('click', resetFilters);
  $('#empty-reset').addEventListener('click', resetFilters);
  document.querySelectorAll('[data-close-detail]').forEach((button) => button.addEventListener('click', closeDetail));
  elements.detailModal.addEventListener('click', (event) => { if (event.target === elements.detailModal) closeDetail(); });
  $('#cart-open').addEventListener('click', openCart);
  $('#cart-close').addEventListener('click', closeCart);
  $('#continue-shopping').addEventListener('click', closeCart);
  elements.cartOverlay.addEventListener('click', (event) => { if (event.target === elements.cartOverlay) closeCart(); });
  elements.checkoutForm.addEventListener('submit', submitCheckout);
  elements.paymentMethod.addEventListener('change', updatePaymentHint);
  elements.trackingForm.addEventListener('submit', trackOrder);
  $('#success-close').addEventListener('click', () => {
    $('#order-success').hidden = true;
    document.body.classList.remove('modal-open');
  });
  $('#order-success').addEventListener('click', (event) => {
    if (event.target === $('#order-success')) {
      $('#order-success').hidden = true;
      document.body.classList.remove('modal-open');
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeDetail();
      closeCart();
      $('#order-success').hidden = true;
      document.body.classList.remove('modal-open');
    }
  });

  (async function init() {
    $('#current-year').textContent = String(new Date().getFullYear());
    renderCart();
    try {
      await Promise.all([loadStore(), loadCategories()]);
      elements.status.textContent = 'Boutique connectée';
      elements.status.classList.add('online');
    } catch (error) {
      elements.status.textContent = 'API indisponible';
      elements.status.classList.add('offline');
      setError(`Connexion impossible à ${API_BASE} : ${error.message}`);
    }
    await loadProducts();
  })();
})();
