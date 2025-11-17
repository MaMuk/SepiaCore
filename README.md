# SepiaCore

SepiaCore is an entity-agnostic application framework that lets you create a relational database accessible via a web frontend without having to code. The name "SepiaCore" comes from the cuttlefish (genus Sepia), which uses mimicry to adapt its appearance to its environment. Similarly, SepiaCore adapts to any data structure you wish, without being tied to specific entities.

This repository contains two main folders:

* `/backend` — PHP application
* `/frontend` — Vue.js frontend

---

## Backend (PHP)

### Requirements

The backend is developed under PHP 8.4 (tested on 8.3 and 8.4) and requires the following PHP extensions:

* `curl`
* `json`
* `mbstring`
* `hash`
* `zlib`
* `pdo_sqlite` (SQLite is default; MySQL and PostgreSQL are also supported via `illuminate/database`)

### Install Dependencies

Navigate to the backend folder and install PHP packages using Composer:

cd backend
composer install

### Run Locally (Development)

For development purposes, you can use PHP’s built-in server:

php -S 0.0.0.0:8001 -t .

---

## Frontend (Vue.js)

The frontend is a Vue.js 3 application built with Vite. It uses Vue Router for navigation, Pinia for state management, and Bootstrap 5 for styling.

### Requirements

- Node.js (v16 or higher recommended)
- npm or yarn

### Install Dependencies

Navigate to the frontend folder and install Node.js packages:

```bash
cd frontend
npm install
```

**Note:** This project uses a patched version of the `winbox` package (v0.2.82). The patch fixes the width calculation for minimized windows. By default, winbox calculates minimized window width based on screen width to arrange windows horizontally at the bottom of the page. However, this application stacks minimized windows vertically in a sidebar (`Sidebar.vue`), so screen width is not relevant. The patch sets a fixed width of 250px for minimized windows. The patch is automatically applied via `patch-package` during `npm install` (see `postinstall` script in `package.json`).

### Configuration

The frontend communicates with the backend via the configured API base URL. You can set this via:

1. **Environment variable** (recommended for development):
   - Create a `.env` file in the `frontend` directory:
     ```
     VITE_API_BASE_URL=http://localhost:8001
     ```

2. **Runtime configuration** (via the application's configuration interface)

### Running the Frontend (Development)

For development, use Vite's development server:

```bash
cd frontend
npm run dev
```

This will start the Vite dev server (typically on `http://localhost:3001`). The dev server includes hot module replacement (HMR) for fast development.

### Building for Production

To build the frontend for production deployment:

```bash
cd frontend
npm run build
```

This creates an optimized production build in the `frontend/dist` directory. This directory should be served by your web server (Nginx, Apache, etc.) in production.

---

## Quick Start

These instructions get the application running quickly for development or testing purposes:

1. **Start the Backend**

cd backend
composer install
php -S 0.0.0.0:8001 -t .

2. **Start the Frontend**

cd frontend
npm install
npm run dev

Open the URL shown in the terminal (typically `http://localhost:3001`) in your browser.

**Note:** The frontend development server runs on port 3001 by default (configurable in `vite.config.js`). The backend should run on a different port (e.g., 8001).

3. **First-Time Installation**

When the app is not yet installed, any request will redirect the user to `/install`. The install page prompts for:

* **Database Name:**
* **Instance Name:** Any identifier for this installation.
* **Admin Username and Password:** Credentials for the first administrator account.

After completing the install form, the backend initializes the database and admin user, and the application is ready to use.

---

## Production Deployment

**Never run the backend in production using PHP's built-in development server (`php -S`)**, as it is not secure and can expose sensitive files—including `.db` database files, configuration files, and vendor libraries—if misconfigured.

**Never run the frontend development server (`npm run dev`) in production.** Always build the frontend for production using `npm run build` and serve the resulting `dist` directory via a proper web server.

For production, use a proper web server such as **Nginx** or **Apache** with PHP-FPM. The provided Nginx configuration demonstrates best practices for:

* Building and serving the Vue.js frontend
* Routing requests
* Serving the backend API
* Denying access to sensitive files (`.db`, config, vendor, etc.)

### Frontend Production Build

Before deploying, build the frontend:

```bash
cd frontend
npm install
npm run build
```

This creates an optimized production build in `frontend/dist`. Deploy this directory to your web server.

Ensure that `.db` database files and configuration files are protected from public access and that file permissions are correctly set. Running development servers in production is a serious security risk and must be avoided.

---

## Example Nginx Configuration

This configuration serves the built Vue.js frontend from `frontend/dist` and routes API requests to the PHP backend:

```
server {
    server_name app.dev.local;
    root /var/www/app/frontend/dist;
    index index.html;
    
    # Serve Vue.js frontend (SPA routing)
    location / {
        try_files $uri $uri/ /index.html;
    }
    
    # Backend API routes
    location /api {
        root /var/www/app;
        try_files /backend$uri /backend$uri/ /backend/index.php$is_args$args;
        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }
    }
    
    # Deny access to sensitive files and folders
    location ~ ^/((config|content|vendor|composer\.(json|lock|phar))(/|$)|(.+/)?\.(?!well-known(/|$))) {
        deny all;
        return 404;
    }
    
    # Deny access to any .db file anywhere
    location ~* \.db$ {
        deny all;
        return 404;
    }
}
```

**Important:** 
- The `root` directive points to `frontend/dist` (the built production files)
- The frontend should be built using `npm run build` before deployment
- API requests are routed to `/api` which proxies to the backend
- Adjust the backend routing path (`/backend`) if your backend is located elsewhere


