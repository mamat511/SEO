# SEO — tableau de bord multi-sites

Outil SEO personnel en Laravel 13 + Livewire 4 :

- **Projets** : autant de sites que nécessaire, avec ou sans propriété Search Console.
- **Vue d'ensemble** : clics, impressions, CTR et position moyenne (28 j. vs 28 j. précédents), courbe jusqu'à 16 mois.
- **Suivi de positions** : mots-clés suivis, historique quotidien, évolution sur 7 jours, page qui se positionne, suggestions de requêtes non suivies.
- **Backlinks** : import des exports CSV du rapport « Liens » de la Search Console, avec liens gagnés et perdus d'un import à l'autre.
- **Audit technique** : crawler interne (robots.txt respecté), erreurs 4xx/5xx, redirections, title/meta/H1, doublons, liens internes cassés, lenteur, contenu faible, noindex, canonical, avec un score de santé.

## Installation

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan app:create-user ton@email.fr
```

## Connexion à la Search Console

1. Dans un projet Google Cloud, active l'API **Google Search Console API**.
2. Crée un identifiant OAuth de type « Application Web » sur https://console.cloud.google.com/apis/credentials
   et ajoute l'URI de redirection autorisée : `APP_URL/google/callback` (ex. `http://localhost:8000/google/callback`).
3. Renseigne `GOOGLE_CLIENT_ID` et `GOOGLE_CLIENT_SECRET` dans `.env`.
4. Dans l'application : **Tableau de bord → Connecter la Search Console**.

Tant que l'écran de consentement OAuth est en mode « Test », ajoute ton adresse Google aux utilisateurs de test.

## Lancer en local

```bash
php artisan serve          # http://localhost:8000
php artisan queue:work     # synchronisations et audits (obligatoire)
php artisan schedule:work  # synchronisation quotidienne à 6 h
```

En production : un worker de file d'attente (Supervisor) et le cron `* * * * * php artisan schedule:run`.

## Limites connues

- La Search Console ne publie ses données qu'avec environ 2 jours de retard, et ne les conserve que 16 mois.
- L'API Search Console ne fournit pas les backlinks : il faut passer par les exports CSV.
- La position affichée est la **position moyenne** Google, pas un relevé ponctuel de la SERP.

## Tests

```bash
php artisan test
```
