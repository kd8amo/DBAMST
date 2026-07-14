# Dev Environment Setup — Docker (PHP 8.3 / Nginx / PostgreSQL 16)

This runs the entire stack in containers, matching the architecture we expect
to use on the production SLES 12 SP5 host once IT approves it. Your VM's
native PHP 8.0.30, Nginx 1.21.5, and PostgreSQL 16.1 are left completely
untouched — none of this conflicts with them.

## Prerequisites

Install Docker and Docker Compose on the VM (openSUSE Leap 15.4):

```bash
sudo zypper install docker docker-compose
sudo systemctl enable --now docker
sudo usermod -aG docker $USER   # log out/in after this so you don't need sudo for docker
```

Verify:
```bash
docker --version
docker compose version
```

## Project layout

```
project/
├── docker-compose.yml
├── .env.docker.example
├── docker/
│   ├── php/
│   │   ├── Dockerfile
│   │   ├── php.ini
│   │   └── opcache.ini
│   └── nginx/
│       └── default.conf
└── (Laravel application code goes here once scaffolded)
```

## First-time setup

1. Copy the env template and adjust the database password:
   ```bash
   cp .env.docker.example .env.docker
   # edit .env.docker - set a real DB_PASSWORD
   ```

2. Build and start the stack:
   ```bash
   docker compose --env-file .env.docker up -d --build
   ```

3. Confirm everything is running:
   ```bash
   docker compose ps
   ```
   You should see `tsm_app`, `tsm_nginx`, `tsm_db`, and `tsm_pgadmin` all `Up`.

4. The application will be reachable at:
   - **App (via Nginx):** http://localhost:8080
   - **pgAdmin (DB browser, dev convenience only):** http://localhost:8081
   - **PostgreSQL (direct connection, e.g. from a desktop DB client):** localhost:5433

   Note: PostgreSQL is mapped to **5433** on the host, not 5432, specifically
   so it doesn't collide with the native PostgreSQL 16.1 already running on
   this VM. The containerized Postgres is a separate, independent database
   used only by this application.

## Day-to-day use

- **Run a command inside the PHP container** (e.g. artisan commands, composer):
  ```bash
  docker compose exec app php artisan migrate
  docker compose exec app composer install
  ```

- **View logs:**
  ```bash
  docker compose logs -f app
  docker compose logs -f webserver
  ```

- **Stop the stack** (keeps data):
  ```bash
  docker compose down
  ```

- **Stop and wipe the database** (fresh start):
  ```bash
  docker compose down -v
  ```

## Why this setup, specifically

- **PHP 8.3 in a container** sidesteps Leap 15.4's native package version
  entirely, and validates the same containerization pattern we'll need for
  the production SLES 12 SP5 host (which can't easily get PHP 8.x natively).
- **Nginx and PostgreSQL are also containerized** even though native versions
  already run on this VM, so the entire stack is portable as one unit — what
  you test here is what ships, just on a different host.
- **PostgreSQL runs on host port 5433** to avoid any conflict with the native
  PostgreSQL 16.1 instance already on this machine. The two are unrelated;
  containerized Postgres has its own data volume.
- **`validate_timestamps=1`** in opcache is a deliberate dev-only setting so
  code edits show up immediately. This must be revisited (set to `0` +
  `php artisan optimize` on deploy) for production — noted in `opcache.ini`.

## Known open item

This stack is for **development only** right now. Production deployment
(whether on SLES 12 SP5 via Docker, or a different IT-approved target) is a
separate decision still pending — see Section 5 of the use cases document.
Nothing here commits you to a specific production hosting choice.
