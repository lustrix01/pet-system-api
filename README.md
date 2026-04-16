# Pet Management System - Backend API

This repository holds our Group REST API Enhancement project.

## Table of Contents
1. [System Overview & Plan](#system-overview-plan)
2. [Architecture and Files](#architecture-and-files)
3. [Run with Docker](#run-with-docker)
4. [Run with XAMPP](#run-with-xampp)
5. [API Actions](#api-actions)

### System Overview & Plan

The goal of this project is to build a centralized REST API that can communicate with multiple different client applications.

Planned architecture:

- **Backend (Server):** PHP REST API connected to MySQL/MariaDB for authentication and pet CRUD.
- **Client 1 (PHP):** Server-side Customer Panel with sessions.
- **Client 2 (JavaScript/HTML):** Client-side Admin Dashboard using Fetch API.

### Architecture and Files

All API logic is centralized in **`src/api.php`** using action-based routing (`POST action=<name>`).

- **`src/api.php`**: Main controller for all API actions.
- **`src/db.php`**: MySQL connection with environment-variable support and local fallbacks.
- **`src/database/user_system.sql`**: Database schema and seed.
- **`Dockerfile` + `docker-compose*.yml`**: Local Docker runtime.

![Database Schema; user(id, username, password) 1 <---> 0...* pet(id, user_id, pet_name, pet_type)](database-schema.png)

### Run with Docker

1. Copy `.env.example` to `.env` if needed, then review DB credentials.
2. From project root, run:
   ```bash
   docker compose up --build
   ```
3. Open:
   - API base: `http://localhost:8080/api.php`
   - Healthcheck: `http://localhost:8080/healthcheck.php`
   - phpMyAdmin: `http://localhost:8081`

The DB is initialized automatically from `src/database/user_system.sql`.

### Run with XAMPP

1. Start **Apache** and **MySQL** in XAMPP.
2. Place this project in `xampp/htdocs/`.
3. Import `src/database/user_system.sql` into DB `user_system` via phpMyAdmin.
4. Use API at:
   - `http://localhost/pet-system-api/src/api.php`
   - `http://localhost/pet-system-api/src/healthcheck.php`

`src/db.php` defaults for XAMPP local mode:

- `DB_HOST=127.0.0.1`
- `DB_USER=root`
- `DB_PASS=` (empty)
- `DB_NAME=user_system`
- `DB_PORT=3306`

### API Actions

Send requests to `src/api.php` with `action=<action_name>`.

- `register` (POST)
- `login` (POST)
- `add_pet` (POST)
- `delete_pet` (POST/DELETE)
- `get_pets` (GET/POST)
- `get_users` (GET/POST)
- `update_pet` (POST/PUT/PATCH)

Compatibility response mode:
- `get_users` and `update_pet` now return `api-playroom`-style payloads by default.
- Add `wrap=1` in query/body to return wrapped format:
  - `{ "status": "...", "message": "...", "data": ... }`
  
