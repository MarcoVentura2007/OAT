# OTA — Circolo Tennis Brescia
## Documentazione tecnica & Guida al Deployment

---

## Architettura

```
circolo-tennis/
│
├── pages/
│   ├── index.html              ← Home page pubblica
│   ├── chi-siamo.html
│   ├── il-circolo.html
│   ├── galleria.html           ← Galleria (chiama API PHP)
│   ├── tariffe.html
│   └── contatti.html
├── style.css
├── main.js
├── .htaccess               ← Routing API + sicurezza
│
├── api/
│   ├── auth/
│   │   ├── login.php       POST /api/auth/login
│   │   └── session.php     POST /api/auth/logout | GET /api/auth/me
│   ├── gallery/
│   │   └── photos.php      GET|POST|PUT|DELETE /api/gallery/*
│   └── admin/
│       └── dashboard.php   GET /api/admin/stats|logs|users
│
├── admin/
│   ├── login.html          ← Pannello accesso admin
│   └── dashboard.html      ← Dashboard gestione completa
│
├── config/
│   └── config.php          ← Configurazione DB e costanti
│
├── includes/
│   ├── Database.php        ← Singleton PDO
│   └── helpers.php         ← Auth, JSON response, logging
│
├── public/
│   └── uploads/            ← Foto caricate (sottocartelle per categoria)
│       └── .htaccess       ← Auto-generato: blocca esecuzione PHP
│
└── database.sql            ← Schema + seed iniziale
```

---

## Requisiti server

| Componente | Versione minima |
|------------|----------------|
| PHP        | 8.1+           |
| MySQL      | 8.0+ oppure MariaDB 10.5+ |
| Apache     | 2.4+ con mod_rewrite |
| Estensioni PHP | pdo_mysql, fileinfo, gd |

---

## Installazione passo per passo

### 1. Database

```sql
-- Connettiti come root e crea DB + utente dedicato
CREATE DATABASE OTA_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ota_user'@'localhost' IDENTIFIED BY 'PASSWORD_SICURA';
GRANT SELECT, INSERT, UPDATE, DELETE ON OTA_DB.* TO 'ota_user'@'localhost';
FLUSH PRIVILEGES;
```

```bash
# Importa lo schema
mysql -u ota_user -p OTA_DB < database.sql
```

### 2. Configurazione

```bash
# Rinomina il file di esempio e compilalo
cp config/config.php config/config.php
nano config/config.php
```

Modifica obbligatoriamente:
```php
define('DB_USER', 'ota_user');
define('DB_PASS', 'PASSWORD_SICURA');   // stessa usata nel CREATE USER
define('APP_URL', 'https://tuodominio.it');
```

### 3. Permessi filesystem

```bash
# La cartella uploads deve essere scrivibile dal web server
chmod -R 755 public/uploads/
chown -R www-data:www-data public/uploads/   # su Debian/Ubuntu
# oppure
chown -R apache:apache public/uploads/        # su CentOS/RHEL
```

### 4. Apache Virtual Host

```apache
<VirtualHost *:443>
    ServerName tuodominio.it
    DocumentRoot /var/www/circolo-tennis

    <Directory /var/www/circolo-tennis>
        AllowOverride All
        Require all granted
    </Directory>

    # SSL (usa Certbot / Let's Encrypt)
    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/tuodominio.it/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/tuodominio.it/privkey.pem
</VirtualHost>
```

```bash
a2enmod rewrite ssl
systemctl restart apache2
```

### 5. Primo accesso admin

1. Vai su `https://tuodominio.it/admin/login.html`
2. Username: `admin` | Password: `Admin2025!`
3. **Cambia subito la password** da Impostazioni → Cambia password

---

## API Reference

### Autenticazione

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| POST | `/api/auth/login` | Login, ritorna token |
| POST | `/api/auth/logout` | Invalida sessione |
| GET  | `/api/auth/me` | Dati utente corrente |
| POST | `/api/auth/change-password` | Cambio password |

**Header richiesto per endpoint protetti:**
```
X-OTA-Token: <token ricevuto al login>
```

**Body login:**
```json
{ "username": "admin", "password": "Admin2025!" }
```

**Risposta login:**
```json
{
  "success": true,
  "token": "a3f9c2...",
  "expires_at": "2025-06-14 22:00:00",
  "user": {
    "id": 1, "username": "admin",
    "full_name": "Amministratore OTA",
    "email": "admin@ctbrescia.it", "role": "superadmin"
  }
}
```

### Galleria (pubblico)

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| GET | `/api/gallery/photos` | Tutte le foto visibili |
| GET | `/api/gallery/photos?cat=campi` | Filtro per categoria |
| GET | `/api/gallery/categories` | Lista categorie |

### Galleria (admin — richiede token)

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| GET    | `/api/gallery/photos/all` | Tutte le foto (anche nascoste) |
| POST   | `/api/gallery/upload` | Carica nuova foto (multipart/form-data) |
| PUT    | `/api/gallery/photos/{id}` | Modifica titolo/categoria/visibilità |
| DELETE | `/api/gallery/photos/{id}` | Elimina foto e file |

**Upload (form-data):**
```
photo      = <file binario>
category   = campi|bar|ristorante|eventi|altro
title      = "Titolo foto"
description = "Descrizione opzionale"
```

### Admin

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| GET | `/api/admin/stats` | Statistiche dashboard |
| GET | `/api/admin/logs?limit=30&offset=0` | Log attività |
| GET | `/api/admin/users` | Lista utenti (solo superadmin) |

---

## Sicurezza

- **Password** cifrate con `bcrypt` (cost 12)
- **Token** generati con `random_bytes(32)` (crittograficamente sicuri)
- **Sessioni** con TTL 8 ore, auto-invalidazione alla scadenza
- **MIME check reale** con `finfo` (non si fida dell'estensione)
- **Directory uploads** protetta da esecuzione PHP via `.htaccess`
- **Prepared statements** PDO su tutte le query (anti SQL injection)
- **Headers di sicurezza** Apache (CSP, X-Frame, XSS protection)
- **Accesso diretto** a `config/` e `includes/` bloccato via rewrite rule
- **Log** di ogni accesso, upload, modifica ed eliminazione

---

## Produzione — checklist

- [ ] Cambiare credenziali DB in `config/config.php`
- [ ] Impostare `APP_URL` corretto con HTTPS
- [ ] Cambiare `APP_ENV` → `'production'`
- [ ] Cambiare password admin dal pannello
- [ ] Configurare backup automatico DB (mysqldump + cron)
- [ ] Configurare backup della cartella `public/uploads/`
- [ ] Installare certificato SSL (Let's Encrypt / Certbot)
- [ ] Aggiungere `config/config.php` al `.gitignore`
- [ ] Verificare permessi cartella uploads (`chmod 755`)
- [ ] Testare upload da interfaccia admin
- [ ] Controllare log Apache per errori

---

## Estensioni future consigliate

- **Resize automatico**: integrare la libreria Intervention Image (PHP)
  per generare miniature all'upload
- **CDN**: spostare gli upload su AWS S3 / Cloudflare R2
- **Multi-admin**: creare utenti aggiuntivi da `/api/admin/users`
- **WebP conversion**: convertire automaticamente JPG/PNG in WebP
- **Backup cloud**: script cron per backup su S3 / Google Drive
- **Autenticazione 2FA**: aggiungere TOTP con Google Authenticator

---

*Sviluppato per Circolo Tennis Brescia ASD · v1.0 · 2025*
