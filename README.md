# Session backend

Ce repository contient le code source de l'api de l'application Session.

Note: Le projet n'est plus maintenu.

[https://app-session.com/](https://app-session.com/)

## Initialisation

dans le `.env` : `APP_ENV=prod`

```
git clone <url>

cd <dossier>

composer install
```

générer les clés jwt

```
php bin/console lexik:jwt:generate-keypair
```

copier le fichier `session-firebase-adminsdk-key.json`

éditer le .env.local avec le nom/mdp de la bdd et les credentials bucket aws et les credentials symfony mailer

## Mercure

MERCURE_PUBLISHER_JWT_KEY='dXXhrkWOY9lLpY3zEUgE6k9QSn5O9fbnfnqFWpT1SzEyywrNjdjz2zhfHywxuVvqusY3WvZYsS1ISL9TzVg3xKCBliMWieCwJwLAl0CheSXDBfgJkluCwF4Or8i3AVe4OHb2L43rlyJLm3SNMrAXy5qSDodd1T3AYiZ5K916LI6lElaUQdro5lxFzECKXz5TgP0uOmnK8uzjMCHfTOdM9glc6Ia6ErJX1uTxpKhjTyIXJQM8cdlb0Hrs2lML5oB4' MERCURE_SUBSCRIBER_JWT_KEY='dXXhrkWOY9lLpY3zEUgE6k9QSn5O9fbnfnqFWpT1SzEyywrNjdjz2zhfHywxuVvqusY3WvZYsS1ISL9TzVg3xKCBliMWieCwJwLAl0CheSXDBfgJkluCwF4Or8i3AVe4OHb2L43rlyJLm3SNMrAXy5qSDodd1T3AYiZ5K916LI6lElaUQdro5lxFzECKXz5TgP0uOmnK8uzjMCHfTOdM9glc6Ia6ErJX1uTxpKhjTyIXJQM8cdlb0Hrs2lML5oB4' ./mercure run -config Caddyfile.dev

## dev

```
symfony serve
```

