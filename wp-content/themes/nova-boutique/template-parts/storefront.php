<?php
/** Storefront markup shared by the homepage and the optional page template. */
defined('ABSPATH') || exit;
?>
<div class="announcement-bar">
<p>Une sélection pensée pour vos projets <span aria-hidden="true">✦</span> Livraison offerte dès le seuil configuré</p>
</div>


<header class="site-header">
<div class="header-inner shell">
<a aria-label="NOVA Boutique, accueil" class="brand" href="#accueil">
<span aria-hidden="true" class="brand-symbol"><span></span><span></span><span></span></span>
<span class="brand-copy"><strong id="store-name">NOVA</strong><small>BOUTIQUE TECHNIQUE</small></span>
</a>
<nav aria-label="Navigation principale" class="main-nav">
<a href="#catalogue">La boutique</a>
<a href="#engagements">Nos engagements</a>
<a href="#suivi">Suivre ma commande</a>
</nav>
<div class="header-actions">
<span class="api-status" id="api-status" role="status">Connexion…</span>
<button aria-label="Ouvrir le panier" class="cart-trigger" id="cart-open" type="button">
<svg aria-hidden="true" viewbox="0 0 24 24"><path d="M3 4h2l2.2 10.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 1.9-1.4L21 8H6"></path><circle cx="10" cy="20" r="1.2"></circle><circle cx="18" cy="20" r="1.2"></circle></svg>
<span>Panier</span><b id="cart-count">0</b>
</button>
</div>
</div>
</header>


<main id="accueil">
<section class="hero shell">
<div class="hero-copy">
<p class="eyebrow"><span></span> L’ÉQUIPEMENT, EN TOUTE SIMPLICITÉ</p>
<h1>Les bons outils.<br/><em>Les bonnes idées.</em></h1>
<p class="hero-lead">Explorez une sélection d’équipements et d’accessoires fiables pour accompagner vos projets, au travail comme à la maison.</p>
<div class="hero-actions">
<a class="button button-dark" href="#catalogue">Découvrir la boutique <span aria-hidden="true">↗</span></a>
<span class="hero-note">Une expérience simple, un service de proximité.</span>
</div>
<div class="hero-metrics">
<div><strong>01</strong><span>Une sélection utile</span></div>
<div><strong>02</strong><span>Des prix transparents</span></div>
<div><strong>03</strong><span>Un suivi de commande</span></div>
</div>
</div>
<div aria-label="Composition graphique de produits et d’équipements" class="hero-art">
<div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
<div class="art-card art-card-main"><span class="art-tag">COLLECTION</span><div class="art-object"><i></i><b></b><span></span></div><div class="art-card-foot"><span>Solutions techniques</span><strong>01 / 03</strong></div></div>
<div class="art-card art-card-small"><span class="art-small-icon">✳</span><strong>Du pratique,<br/>du durable.</strong><small>À chaque projet<br/>sa solution.</small></div>
<div class="art-stamp">NOVA<br/><span>EST. 2026</span></div>
</div>
</section>
<section aria-label="Nos engagements" class="trust-strip shell" id="engagements">
<div class="trust-item"><span class="trust-icon">↗</span><div><strong>Une sélection soignée</strong><small>Des produits choisis avec attention</small></div></div>
<div class="trust-item"><span class="trust-icon">◇</span><div><strong>Paiement flexible</strong><small>À la livraison ou par virement</small></div></div>
<div class="trust-item"><span class="trust-icon">⌁</span><div><strong>Suivi de commande</strong><small>Consultez l’avancement en ligne</small></div></div>
<div class="trust-item"><span class="trust-icon">♡</span><div><strong>À votre écoute</strong><small>Un contact direct avec la boutique</small></div></div>
</section>
<section class="shop-section" id="catalogue">
<div class="shell">
<div class="section-heading">
<div><p class="eyebrow"><span></span> LA BOUTIQUE</p><h2>À découvrir <em>maintenant.</em></h2><p class="section-description">Trouvez facilement le produit qui correspond à vos besoins.</p></div>
<div class="section-aside"><span class="section-index">01 — PRODUITS</span><span class="section-line"></span></div>
</div>
<div class="catalog-layout">
<aside aria-label="Filtres produits" class="filters-panel">
<div class="filters-top"><h3>Affiner la sélection</h3><button class="text-button" id="reset-filters" type="button">Tout effacer</button></div>
<form id="filter-form">
<label class="field-label" for="search">Recherche</label>
<div class="search-field"><span aria-hidden="true">⌕</span><input autocomplete="off" id="search" name="q" placeholder="Nom, référence, marque…" type="search"/></div>
<label class="field-label" for="category">Catégorie</label>
<select id="category" name="category"><option value="">Toutes les catégories</option></select>
<label class="field-label" for="brand">Marque</label>
<input id="brand" name="brand" placeholder="Toutes les marques" type="text"/>
<div class="filter-divider"></div>
<p class="filter-title">Budget</p>
<div class="field-pair">
<div><label class="field-label" for="min-price">Minimum</label><input id="min-price" min="0" name="min_price" placeholder="0" step="0.01" type="number"/></div>
<div><label class="field-label" for="max-price">Maximum</label><input id="max-price" min="0" name="max_price" placeholder="—" step="0.01" type="number"/></div>
</div>
<label class="field-label" for="stock">Disponibilité</label>
<select id="stock" name="stock"><option value="">Toutes</option><option value="instock">En stock</option><option value="backorder">Sur commande</option><option value="outofstock">Épuisé</option></select>
<label class="check-row"><input id="on-sale" type="checkbox"/><span>Afficher les promotions</span></label>
<div class="filter-divider"></div>
<p class="filter-title">Caractéristiques</p>
<label class="field-label" for="attribute-key">Nom de caractéristique</label>
<input id="attribute-key" placeholder="ex. couleur" type="text"/>
<label class="field-label" for="attribute-value">Valeur recherchée</label>
<input id="attribute-value" placeholder="ex. noir" type="text"/>
<button class="button button-accent apply-button" type="submit">Appliquer les filtres <span>→</span></button>
</form>
</aside>
<section aria-label="Produits disponibles" class="results">
<div class="results-toolbar">
<div><p class="results-kicker">NOTRE SÉLECTION</p><h3>Les indispensables</h3><p class="muted" id="result-count">Chargement des produits…</p></div>
<label class="sort-control"><span>Trier par</span><select id="orderby"><option value="newest">Nouveautés</option><option value="popular">Les plus demandés</option><option value="price_asc">Prix croissant</option><option value="price_desc">Prix décroissant</option></select></label>
</div>
<div class="notice notice-error" hidden="" id="error-message" role="alert"></div>
<div aria-busy="true" aria-live="polite" class="product-grid" id="product-grid"></div>
<div class="empty-state" hidden="" id="empty-state"><div class="empty-mark">⌕</div><h3>Aucun produit trouvé</h3><p>Essayez d’autres mots-clés ou réinitialisez vos filtres.</p><button class="button button-outline" id="empty-reset" type="button">Réinitialiser les filtres</button></div>
<nav aria-label="Pages de produits" class="pagination" id="pagination"></nav>
</section>
</div>
</div>
</section>
<section class="track-section shell" id="suivi">
<div class="track-copy"><p class="eyebrow"><span></span> APRÈS VOTRE ACHAT</p><h2>Votre commande,<br/><em>en un coup d’œil.</em></h2><p>Retrouvez son état avec la référence reçue par e-mail et l’adresse e-mail utilisée lors de l’achat.</p></div>
<form class="tracking-card" id="tracking-form"><div class="tracking-card-head"><span class="tracking-icon">⌁</span><div><strong>Suivre une commande</strong><small>Renseignez les deux informations demandées.</small></div></div><label class="field-label" for="tracking-number">Référence de commande</label><input id="tracking-number" name="order_number" placeholder="CMD-20261115-ABCDE" required=""/><label class="field-label" for="tracking-email">Adresse e-mail</label><input id="tracking-email" name="email" placeholder="vous@exemple.com" required="" type="email"/><button class="button button-dark" type="submit">Consulter ma commande <span>→</span></button><div aria-live="polite" class="tracking-result" hidden="" id="tracking-result"></div></form>
</section>
</main>


<footer class="site-footer">
<div class="footer-main shell"><a class="brand brand-footer" href="#accueil"><span aria-hidden="true" class="brand-symbol"><span></span><span></span><span></span></span><span class="brand-copy"><strong id="footer-store-name">NOVA</strong><small>BOUTIQUE TECHNIQUE</small></span></a><p>Des solutions utiles, un parcours d’achat simple.</p><div class="footer-contact"><span>UNE QUESTION ?</span><a href="mailto:contact@example.com" id="contact-email">contact@example.com</a></div></div>
<div class="footer-bottom shell"><span>© <span id="current-year"></span> <span id="copyright-store">NOVA Boutique</span>. Tous droits réservés.</span><span>Site réalisé par <strong>Karam Erramach</strong> · Propulsé par WordPress</span></div>
</footer>


<div class="modal-backdrop" hidden="" id="detail-modal">
<section aria-labelledby="detail-title" aria-modal="true" class="detail-dialog" role="dialog">
<button aria-label="Fermer" class="modal-close" data-close-detail="" type="button">×</button>
<div id="detail-content"></div>
</section>
</div>


<div class="drawer-backdrop" hidden="" id="cart-overlay">
<aside aria-labelledby="cart-title" aria-modal="true" class="cart-drawer" role="dialog">
<div class="drawer-head"><div><p class="eyebrow"><span></span> VOTRE SÉLECTION</p><h2 id="cart-title">Mon panier <span id="cart-line-count">(0)</span></h2></div><button aria-label="Fermer le panier" class="modal-close" id="cart-close" type="button">×</button></div>
<div class="cart-lines" id="cart-lines"></div>
<div class="cart-empty" id="cart-empty"><span>◇</span><strong>Votre panier est encore vide.</strong><p>Ajoutez quelques produits pour commencer.</p><button class="button button-outline" id="continue-shopping" type="button">Continuer mes achats</button></div>
<div class="cart-checkout" hidden="" id="cart-checkout">
<div class="cart-totals"><div><span>Sous-total</span><strong id="cart-subtotal">—</strong></div><div><span>Livraison</span><strong id="cart-shipping">—</strong></div><div class="cart-grand-total"><span>Total estimé</span><strong id="cart-total">—</strong></div><small id="shipping-hint"></small></div>
<form class="checkout-form" id="checkout-form">
<div class="checkout-heading"><span>ÉTAPE 01</span><h3>Vos coordonnées</h3></div>
<div class="field-pair"><div><label class="field-label" for="first-name">Prénom</label><input autocomplete="given-name" id="first-name" name="first_name" required=""/></div><div><label class="field-label" for="last-name">Nom</label><input autocomplete="family-name" id="last-name" name="last_name" required=""/></div></div>
<label class="field-label" for="customer-email">E-mail</label><input autocomplete="email" id="customer-email" name="email" required="" type="email"/>
<label class="field-label" for="customer-phone">Téléphone</label><input autocomplete="tel" id="customer-phone" name="phone" placeholder="+212 …" required="" type="tel"/>
<label class="field-label" for="customer-address">Adresse de livraison</label><textarea autocomplete="street-address" id="customer-address" name="address" required="" rows="2"></textarea>
<label class="field-label" for="customer-city">Ville</label><input autocomplete="address-level2" id="customer-city" name="city" required=""/>
<label class="field-label" for="customer-note">Note (facultatif)</label><textarea id="customer-note" name="note" placeholder="Précision pour la livraison…" rows="2"></textarea>
<div class="checkout-heading checkout-heading-payment"><span>ÉTAPE 02</span><h3>Mode de paiement</h3></div>
<label class="field-label" for="payment-method">Choisir un moyen de paiement</label><select id="payment-method" name="payment_method"></select>
<div class="payment-hint" id="payment-hint"></div>
<div class="notice notice-error" hidden="" id="checkout-error" role="alert"></div>
<button class="button button-accent checkout-submit" id="checkout-submit" type="submit">Confirmer ma commande <span>→</span></button>
<p class="checkout-legal">En confirmant, vous acceptez d’être contacté au sujet de votre commande.</p>
</form>
</div>
</aside>
</div>


<div class="success-backdrop" hidden="" id="order-success">
<section aria-labelledby="success-title" aria-modal="true" class="success-dialog" role="dialog"><div class="success-mark">✓</div><p class="eyebrow"><span></span> COMMANDE ENREGISTRÉE</p><h2 id="success-title">Merci pour votre confiance.</h2><p id="success-message">Votre commande a bien été enregistrée.</p><div class="success-reference"><span>VOTRE RÉFÉRENCE</span><strong id="success-order-number"></strong></div><p class="success-total">Total : <strong id="success-total"></strong></p><button class="button button-dark" id="success-close" type="button">Retour à la boutique <span>→</span></button></section>
</div>
