# NOVA Boutique — Thème WordPress + WP Catalogue Commerce

**Auteur : Karam Erramach**

NOVA Boutique est une petite boutique e-commerce construite avec WordPress. Le projet est livré sous forme de **deux composants WordPress installables** : une extension qui fournit les produits, le stock et les commandes via une API REST personnalisée, et un véritable thème WordPress classique qui affiche la vitrine.

> Le frontend n’est pas un dossier HTML à publier manuellement : il est inclus dans le thème installable `NOVA Boutique`. Le thème s’installe depuis **Apparence → Thèmes → Ajouter un thème → Téléverser un thème**.

## Livrables

- **Extension — WP Catalogue Commerce** (`wp-catalogue-commerce-plugin.zip`) : modèle de données, API REST, gestion des produits, stock, commandes et paramètres de boutique.
- **Thème — NOVA Boutique** (`nova-boutique-theme.zip`) : vitrine responsive avec recherche, filtres, fiches produit, panier, formulaire de commande et suivi. Le thème est installable depuis l’administration WordPress comme n’importe quel thème classique.
- **Projet complet** : les deux composants réunis sous `wp-content/plugins/` et `wp-content/themes/`.

## Fonctionnalités

### Catalogue et gestion commerciale

- Type de contenu `catalog_product` et taxonomie hiérarchique des catégories.
- SKU unique, marque, prix normal et prix promotionnel.
- Gestion du stock, disponibilité et décrémentation à la commande.
- Produits mis en avant et compteur de ventes.
- Image principale, description et caractéristiques techniques en JSON.
- Recherche par titre, description, référence et marque.
- Filtres par catégorie, marque, prix, disponibilité, promotion et caractéristique.
- Pagination et tri par nouveautés, popularité ou prix.

### Parcours d’achat

- Panier persistant dans le navigateur.
- Fiche détaillée et ajout au panier.
- Modification des quantités et suppression des articles.
- Commande invité avec coordonnées, téléphone, adresse, ville et note.
- Recalcul des prix côté serveur ; les prix fournis par le navigateur ne sont jamais utilisés.
- Frais de livraison et seuil de livraison offerte configurables.
- Paiement à la livraison et virement bancaire activables séparément.
- Commande enregistrée dans WordPress avec détail figé des montants.
- Réservation du stock suivi au moment de la commande.
- E-mail de confirmation selon la configuration e-mail du serveur.
- Suivi public par référence de commande et adresse e-mail.
- Gestion des statuts et restauration du stock lorsqu’une commande est annulée.

### Vitrine NOVA

- Vrai thème WordPress classique, téléversable en ZIP.
- Accueil boutique prêt à l’emploi via `front-page.php`.
- Modèle de page alternatif « NOVA Boutique — Vitrine ».
- Interface française responsive, identité visuelle vert sauge, anthracite et terracotta.
- Recherche dynamique, filtres, tri, pagination, produits en promotion, panier, commande et suivi.
- URL de l’API configurée automatiquement avec `rest_url()` : aucune URL `example.com` à remplacer.

## Prérequis

- WordPress 6.4 ou supérieur.
- PHP 8.1 ou supérieur.
- MySQL 5.7+ ou MariaDB compatible avec les fonctions JSON utilisées par le filtre d’attributs.
- HTTPS recommandé.

## Installation dans WordPress

### 1. Installer l’extension commerce

1. Dans l’administration WordPress, ouvrez **Extensions → Ajouter une extension → Téléverser une extension**.
2. Téléversez `wp-catalogue-commerce-plugin.zip` et cliquez sur **Installer**, puis **Activer**.
3. L’activation crée/met à niveau la table de catalogue et enregistre les types de contenu **Produits** et **Commandes**.

### 2. Installer le thème NOVA Boutique

1. Ouvrez **Apparence → Thèmes → Ajouter un thème → Téléverser un thème**.
2. Sélectionnez `nova-boutique-theme.zip`.
3. Cliquez sur **Installer**, puis **Activer**.
4. Le thème affiche automatiquement la vitrine NOVA sur la page d’accueil WordPress. Vous n’avez pas à téléverser un dossier frontend séparé ni à créer manuellement une page HTML.

Pour afficher la boutique sur une page spécifique au lieu de la page d’accueil, créez/modifiez une page et choisissez le modèle **NOVA Boutique — Vitrine** dans les attributs de page.

### 3. Configurer la boutique

1. Allez dans **Produits → Paramètres boutique**.
2. Configurez le nom, la devise (MAD par défaut), l’adresse e-mail, les frais de livraison et le seuil de livraison offerte.
3. Activez les moyens de paiement souhaités : paiement à la livraison et/ou virement bancaire.
4. Enregistrez les paramètres.

### 4. Ajouter vos produits

Dans **Produits → Ajouter un produit** :

1. Saisissez le titre, la description et le résumé.
2. Définissez un SKU unique, une marque et un prix normal.
3. Ajoutez un prix promotionnel inférieur au prix normal si nécessaire.
4. Définissez la devise du produit (code ISO à trois lettres, par exemple `MAD`).
5. Activez **Gérer automatiquement le stock** pour suivre et décrémenter les quantités.
6. Activez **Mettre en avant** pour distinguer le produit.
7. Saisissez les caractéristiques JSON et choisissez les catégories.
8. Définissez l’image mise en avant avec le panneau WordPress habituel.

Exemple de caractéristiques :

```json
{
  "couleur": "noir",
  "puissance": "1200 W",
  "garantie": "2 ans",
  "connectique": "USB-C"
}
```

## Architecture des fichiers

```text
wp-catalog-api/
├── README.md
└── wp-content/
    ├── plugins/
    │   └── wp-catalog-api/
    │       └── wp-catalog-api.php
    └── themes/
        └── nova-boutique/
            ├── style.css
            ├── functions.php
            ├── header.php
            ├── footer.php
            ├── index.php
            ├── front-page.php
            ├── page-templates/
            │   └── storefront.php
            ├── template-parts/
            │   └── storefront.php
            └── assets/js/app.js
```

## API REST personnalisée

URL de base :

```text
https://VOTRE-SITE/wp-json/wpcatalog/v1
```

### Routes publiques

| Méthode | Route | Description |
|---|---|---|
| `GET` | `/store` | Paramètres publics de la boutique |
| `GET` | `/products` | Recherche, filtres, tri et pagination |
| `GET` | `/products/{id}` | Détail d’un produit publié |
| `GET` | `/categories` | Catégories non vides |
| `POST` | `/orders/checkout` | Validation panier et commande invité |
| `POST` | `/orders/track` | Suivi avec référence et e-mail |

Paramètres de `GET /products` : `q`, `category`, `brand`, `min_price`, `max_price`, `stock`, `on_sale`, `featured`, `attr[cle]`, `orderby`, `page` et `per_page`.

Exemple :

```bash
curl 'https://VOTRE-SITE/wp-json/wpcatalog/v1/products?q=adaptateur&on_sale=1&min_price=50&page=1&per_page=9'
```

### Exemple de commande

`POST /orders/checkout` accepte les produits et quantités ainsi que les coordonnées du client. Le serveur recharge les données produits, valide le stock, recalcule les prix et crée la commande.

```json
{
  "customer": {
    "first_name": "Sara",
    "last_name": "Amrani",
    "email": "sara@example.com",
    "phone": "+212600000000",
    "address": "12 rue des Fleurs",
    "city": "Casablanca",
    "note": "Appeler à l’arrivée"
  },
  "payment_method": "cod",
  "items": [
    {"product_id": 42, "quantity": 2},
    {"product_id": 51, "quantity": 1}
  ]
}
```

Moyens de paiement actuels : `cod` et `bank_transfer`. Il ne s’agit pas d’une passerelle carte bancaire en ligne. Stripe, PayPal ou un prestataire local nécessitent une intégration supplémentaire.

### Routes privées

| Méthode | Route | Autorisation |
|---|---|---|
| `POST` | `/products` | Connecté + `edit_catalog_products` |
| `PUT` / `PATCH` | `/products/{id}` | Capacité de modification + permission objet |
| `DELETE` | `/products/{id}` | Capacité de suppression + permission objet |
| `GET` | `/me` | Utilisateur WordPress connecté |
| `GET` | `/admin/stats` | Administrateur (`manage_options`) |

L’authentification réutilise les cookies + nonce REST WordPress pour le même domaine, ou les mots de passe d’application en HTTPS côté serveur. Ne placez jamais d’identifiants dans le JavaScript public.

## Sécurité, tests et limites

- Les prix et le total d’une commande sont recalculés côté serveur.
- Les écritures produit sont protégées par les capacités WordPress et les permissions par objet.
- Les valeurs, devises, quantités, catégories et attributs sont validés côté serveur.
- Les requêtes SQL utilisent des paramètres préparés et les tris une liste autorisée.
- Le thème charge les scripts uniquement sur la page d’accueil ou le modèle boutique.
- Le thème calcule automatiquement le chemin REST du site WordPress, même si WordPress est installé dans un sous-répertoire.
- Les e-mails nécessitent un serveur e-mail fonctionnel ou un plugin SMTP.
- Les paiements actuels sont à la livraison et par virement. Aucune transaction bancaire ne peut être encaissée par carte sans passerelle dédiée.

Les vérifications statiques PHP et JavaScript peuvent être exécutées avec :

```bash
php -l wp-content/plugins/wp-catalog-api/wp-catalog-api.php
find wp-content/themes/nova-boutique -name '*.php' -print0 | xargs -0 -n1 php -l
node --check wp-content/themes/nova-boutique/assets/js/app.js
```

Une validation finale doit être faite sur un WordPress de test avec les permaliens activés, l’extension et le thème actifs, quelques produits créés et le système d’e-mail configuré.
