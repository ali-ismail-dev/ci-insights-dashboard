# Deploying CI Insights Dashboard to Render.com

This guide walks through deploying the Laravel 11 + React application to Render.com's serverless infrastructure.

## Prerequisites

- A Render.com account (free tier available)
- Your GitHub repository pushed and public (or private with Render access)
- MySQL database credentials ready
- API keys for external services (Meilisearch, Redis, etc.)

## Architecture on Render

Instead of the Docker Compose setup, Render simplifies infrastructure:

| Local | Render |
|-------|--------|
| docker-compose (app + nginx + mysql + redis) | Managed services (no Dockerfile for web server) |
| localStorage PHP-FPM | PHP-FPM on container |
| nginx for routing | Render's reverse proxy handles routing |
| MeiliSearch container | External MeiliSearch or self-hosted |

## Deployment Steps

### Step 1: Create Environment Variables for Render

Log into your Render dashboard and create a new **Web Service**:

1. **Connect Repository** → Select your GitHub repo
2. **Build Command**: (leave empty if using render.yaml)
3. **Start Command**: (automatically uses Dockerfile entrypoint)
4. **Environment** → Add variables or use render.yaml

**Manual Setup (Web Service):**

- Name: `ci-insights-dashboard`
- Runtime: Docker
- Dockerfile: `backend/Dockerfile.render`
- Build Context: `.` (root)

### Step 2: Create Database Service (MySQL)

1. In Render dashboard → **Databases** → **New Database**
   - Confirm Database: `ci_insights`
   - Username: `render`
   - Render generates a strong password

2. Copy the connection details; you'll use them for environment variables

### Step 3: Create Redis Service (optional, for caching/queue)

1. **Redis** → **New Redis Instance**
   - Plan: Starter (or higher if needed)
   - Copy connection details

### Step 4: Add Environment Variables to Web Service

In the Web Service settings → **Environment**:

```
APP_NAME=CI Insights Dashboard
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-service-name.render.com

DB_CONNECTION=mysql
DB_HOST=mysql-host-from-render.render.com
DB_PORT=3306
DB_DATABASE=ci_insights
DB_USERNAME=render
DB_PASSWORD=<password-from-render-mysql>

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis-host-from-render.render.com
REDIS_PORT=6379
REDIS_PASSWORD=<password-from-render-redis>

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=https://your-meilisearch.com
MEILISEARCH_KEY=your-key-here

APP_KEY=base64:generate-with-php-artisan-key-generate

# GitHub OAuth (if using)
GITHUB_CLIENT_ID=your-client-id
GITHUB_CLIENT_SECRET=your-client-secret
```

### Step 5: Deploy

1. Click **Deploy**
2. Render builds the Docker image, runs migrations, and starts the app
3. Check logs at **Logs** tab if anything fails

## Troubleshooting

### "Permission denied" on error_log

This was the original nginx issue. The new `Dockerfile.render` uses PHP-FPM only, so it won't occur.

### Database connection timeouts

- Ensure MySQL service is running and healthy in Render dashboard
- Check DB credentials in environment variables
- Verify firewall rules allow DB connections from web service

### MeiliSearch unreachable

- If using external MeiliSearch: ensure `MEILISEARCH_HOST` and `MEILISEARCH_KEY` are correct
- For self-hosted: Deploy as separate Render service and use its connection string

### Out of memory or slow requests

- Increase PHP-FPM workers in `docker-entrypoint.sh`
- Upgrade Render plan (Starter has 0.5 GB RAM)
- Consider horizontal scaling with multiple web service instances

## Post-Deployment

Once deployed and healthy:

```bash
# Verify database is populated
curl https://your-service-name.render.com/api/health

# Check dashboard at
https://your-service-name.render.com/

# Log in with credentials you set locally (or create new account on registration page)
```

## Local Development vs Production

**Local (docker-compose):**
- Bundled nginx + PHP-FPM
- Local MySQL, Redis, MeiliSearch
- File-based logging
- Hot-reloading with Vite

**Production (Render):**
- Managed MySQL, Redis
- External or managed MeiliSearch
- Render's reverse proxy (no nginx in container)
- Structured logging to Render logs
- Auto-scaling and monitoring via Render dashboard

## Database Migrations

Migrations run automatically in the `docker-entrypoint.sh`. If you need to run them manually:

```bash
# SSH into Render web service shell
# Then run:
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder
```

## Monitoring & Logs

**In Render Dashboard:**
- **Logs** → Real-time PHP-FPM + application logs
- **Metrics** → CPU, Memory, Network
- **Events** → Deploy history and alerts

## Next Steps

1. Configure GitHub OAuth for production (get credentials from GitHub Settings)
2. Set up custom domain (Render → **Custom Domain**)
3. Enable auto-deploy on push (Render → **Auto-Deploy** → "Yes")
4. Monitor logs for any runtime issues

For more help: https://render.com/docs
