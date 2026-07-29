# WordPress Docker Test Environment Guide

This repository contains a full Docker test environment running WordPress, MariaDB, and WP-CLI. It is pre-configured with sample content, pretty permalinks (`/%postname%/`), custom PHP settings, and auto-activation of the **AI Visibility Scanner** plugin.

---

## 🚀 Quick Start

### 1. Launch the Environment
On Windows, double click `docker-start.bat` or run:
```bash
docker compose up -d
```

### 2. Access the WordPress Test Site
- **Website URL:** [http://localhost:8080](http://localhost:8080)
- **WP Admin Dashboard:** [http://localhost:8080/wp-admin](http://localhost:8080/wp-admin)
- **Admin Username:** `admin`
- **Admin Password:** `password123`

---

## 🛠 Features Included

- **Hot-Reloading Plugin Mount:** The root workspace directory `./` is mounted directly into `/var/www/html/wp-content/plugins/ai-visibility-scanner`. Any changes made to code files in your editor will reflect instantly in WordPress.
- **Pre-populated Sample Data:** Includes Home page, About Us, Services, and blog posts with structured headings (H1/H2/H3), categories (`Artificial Intelligence`, `SEO & Optimization`, `Technology & Web`), and text content tailored for testing AI crawlers and LLM audit tools.
- **VPS / Shared Hosting Realism:**
  - Apache `.htaccess` rewrite rules enabled (`/%postname%/` permalink structure).
  - WP REST API active (`http://localhost:8080/wp-json/`).
  - PHP settings (`memory_limit = 256M`, `upload_max_filesize = 64M`, `max_execution_time = 300`).
  - WordPress Debug logging enabled to `/var/www/html/wp-content/debug.log`.

---

## 💻 Useful WP-CLI & Docker Commands

### Run WP-CLI Commands
You can run any WP-CLI command inside the `avs_wordpress_cli` container:
```bash
# Check plugin status
docker compose run --rm wpcli wp plugin list

# Activate or deactivate plugin
docker compose run --rm wpcli wp plugin activate ai-visibility-scanner

# Check database status
docker compose run --rm wpcli wp db check
```

### View Logs
```bash
# View WordPress server logs
docker compose logs -f wordpress

# View debug log
docker compose exec wordpress tail -f /var/www/html/wp-content/debug.log
```

### Stop & Reset Environment
```bash
# Stop containers
docker compose down

# Reset database & site data completely
docker compose down -v
docker compose up -d
```
