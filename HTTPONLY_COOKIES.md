# HttpOnly Cookies Implementation

This application supports both localStorage and httpOnly cookies for token storage, allowing you to switch between them based on environment.

## Configuration

### Backend Configuration

Edit `backend/config/install.php`:

```php
'use_httponly_cookies' => false, // Set to true in production with HTTPS
```

### Frontend Configuration

Edit `.env` file in the frontend directory:

```env
# Development (localStorage)
VITE_USE_HTTPONLY_COOKIES=false

# Production (httpOnly cookies)
VITE_USE_HTTPONLY_COOKIES=true
```

**Important:** The backend and frontend configs should match!

## How It Works

### Development Mode (localStorage)
- `use_httponly_cookies = false` (backend)
- `VITE_USE_HTTPONLY_COOKIES=false` (frontend)
- Token stored in localStorage (accessible to JavaScript)
- Token sent via `Authorization: Bearer <token>` header
- Works without HTTPS (good for local development)

### Production Mode (httpOnly Cookies)
- `use_httponly_cookies = true` (backend)
- `VITE_USE_HTTPONLY_COOKIES=true` (frontend)
- Token stored in httpOnly cookie (NOT accessible to JavaScript - XSS protection)
- Token sent automatically by browser via cookies
- Requires HTTPS for `secure` flag (recommended)
- Better security against XSS attacks

## Security Features

When httpOnly cookies are enabled:
- **XSS Protection**: JavaScript cannot access the token (httpOnly flag)
- **CSRF Protection**: SameSite=Lax prevents cross-site requests
- **Secure Flag**: Only sent over HTTPS (when HTTPS is detected)

## Token Priority

The backend checks for tokens in this order:
1. `Authorization: Bearer <token>` header (highest priority)
2. `auth_token` cookie (if httpOnly cookies enabled)
3. Query string `?token=...` (if `allow_query_token` enabled)

This allows both methods to work simultaneously during migration.

## Migration Guide

1. **Development**: Keep both configs set to `false`
2. **Testing**: Test with httpOnly cookies enabled locally (may need HTTPS setup)
3. **Production**: Set both to `true` and ensure HTTPS is configured

## Notes

- The backend always returns the token in the JSON response (for localStorage fallback)
- When httpOnly cookies are enabled, the frontend ignores the token in the response
- Cookies are automatically sent by the browser (no manual header needed)
- `withCredentials` is set conditionally in axios (only when httpOnly cookies enabled)

## Development Testing with httpOnly Cookies

To test httpOnly cookies in development, you need HTTPS. Here's how to set it up:

### Step 1: Generate Self-Signed Certificates

Run the certificate generation script:

```bash
./generate-dev-certs.sh
```

This creates certificates in the `certs/` directory:
- `backend-cert.pem` and `backend-key.pem` for the backend
- `frontend-cert.pem` and `frontend-key.pem` for the frontend

### Step 2: Trust the Certificates (Manjaro/Arch Linux)

**Option A: System-wide (recommended)**

```bash
# Install ca-certificates if not already installed
sudo pacman -S ca-certificates

# Copy certificates to system trust store
sudo cp certs/backend-cert.pem /usr/local/share/ca-certificates/backend-dev.crt
sudo cp certs/frontend-cert.pem /usr/local/share/ca-certificates/frontend-dev.crt

# Update certificate store
sudo update-ca-certificates
```

**Option B: Browser-only**

- **Chrome/Edge**: Settings > Privacy and Security > Security > Manage certificates > Authorities > Import
- **Firefox**: Settings > Privacy & Security > Certificates > View Certificates > Authorities > Import

### Step 3: Configure Backend for HTTPS

**Option A: Using stunnel (recommended for PHP built-in server)**

1. Install stunnel:
```bash
sudo pacman -S stunnel
```

2. Start backend with HTTPS:
```bash
./start-https-backend.sh 8001
```

**Option B: Using nginx as reverse proxy**

Create an nginx config that proxies to PHP and handles SSL:

```nginx
server {
    listen 8443 ssl;
    server_name localhost;
    
    ssl_certificate /path/to/certs/backend-cert.pem;
    ssl_certificate_key /path/to/certs/backend-key.pem;
    
    location / {
        proxy_pass http://127.0.0.1:8001;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**Option C: Using Caddy (automatic HTTPS)**

Caddy automatically handles HTTPS with self-signed certs in development.

### Step 4: Configure Frontend for HTTPS

Vite will automatically use HTTPS if certificates are found in `certs/` directory.

1. Ensure certificates are generated (Step 1)
2. Start frontend:
```bash
cd frontend
npm run dev
```

Vite will start on `https://localhost:3001` automatically.

### Step 5: Update Configuration

**Backend** (`backend/config/install.php`):
```php
'use_httponly_cookies' => true,
```

**Frontend** (`.env` file):
```env
VITE_USE_HTTPONLY_COOKIES=true
VITE_API_BASE_URL=https://localhost:8001
```

### Step 6: Test

1. Start backend with HTTPS (see Step 3)
2. Start frontend (Step 4)
3. Open `https://localhost:3001` in your browser
4. Accept the security warning (self-signed certificate)
5. Login and verify cookies are set (check DevTools > Application > Cookies)

## Cross-Origin Setup

When frontend and backend run on different domains/ports, ensure:

1. **Backend CORS** is configured correctly (automatically handled when `use_httponly_cookies` is enabled)
2. **Frontend** uses the correct API URL in `.env`:
   ```env
   VITE_API_BASE_URL=https://backend-domain:port
   ```
3. **Backend** allows the frontend origin (currently allows all origins in dev mode)

## Cordova Webview Considerations

**Important**: httpOnly cookies may not work in Cordova webviews without HTTPS, even in development.

### Options for Cordova:

1. **Use HTTPS in Cordova** (recommended):
   - Configure Cordova to use HTTPS
   - Use self-signed certificates and trust them in the webview
   - Set `VITE_USE_HTTPONLY_COOKIES=true`

2. **Use localStorage in Cordova** (fallback):
   - Keep `VITE_USE_HTTPONLY_COOKIES=false` for Cordova builds
   - Accept the XSS risk (mitigate with Content Security Policy)
   - Use httpOnly cookies only in production web deployments

3. **Hybrid Approach**:
   - Detect if running in Cordova webview
   - Use localStorage in Cordova, httpOnly cookies in browsers
   - Requires custom detection logic

### Cordova Configuration Example:

If using Cordova with HTTPS, update `config.xml`:

```xml
<access origin="https://your-backend-domain" />
<allow-navigation href="https://your-backend-domain/*" />
```

And ensure the webview allows cookies:
```xml
<preference name="CookieEnabled" value="true" />
```

## Troubleshooting

### CORS Errors with Credentials

If you see: `Credential is not supported if the CORS header 'Access-Control-Allow-Origin' is '*'`

**Solution**: Ensure `use_httponly_cookies` is `false` in backend config, OR ensure backend sends specific origin (not `*`) when httpOnly cookies are enabled.

### Certificate Warnings

Self-signed certificates will show security warnings. This is normal in development:
- Click "Advanced" → "Proceed to localhost" (Chrome)
- Click "Advanced" → "Accept the Risk and Continue" (Firefox)

### Cookies Not Sent

Check:
1. `withCredentials: true` is set (only when httpOnly enabled)
2. Backend sends `Access-Control-Allow-Credentials: true`
3. Backend allows specific origin (not `*`)
4. Using HTTPS (required for secure cookies)
5. Same domain or proper CORS setup for cross-origin

### PHP Server HTTPS Issues

PHP built-in server doesn't support HTTPS natively. Use:
- stunnel (see Step 3, Option A)
- nginx reverse proxy (see Step 3, Option B)
- Caddy server (see Step 3, Option C)
- Or use a proper web server (Apache/Nginx) for testing

