# 4app Frontend - Vue.js

A modern Vue.js 3 frontend application with Bootstrap 5 styling for the 4app.melmuk.at backend.

## Features

- Vue 3 with Composition API
- Bootstrap 5 for styling
- Pinia for state management
- Vue Router for navigation
- Toast notification system
- Authentication with token-based login
- Backend health check on startup
- Responsive design

## Setup

1. Install dependencies:
```bash
npm install
```

2. Create a `.env` file (optional):
```env
VITE_API_BASE_URL=http://localhost:8000
# VITE_ALLOWED_HOSTS=dev-machine.local
```
   - To allow additional dev hosts for the Vite server, set `VITE_ALLOWED_HOSTS` as a comma-separated list (e.g., `host1.local,host2.local`).

3. Start development server:
```bash
npm run dev
```

4. Build for production:
```bash
npm run build
```

## Project Structure

```
frontend-vue/
├── src/
│   ├── assets/          # CSS and static assets
│   ├── components/      # Vue components
│   │   ├── Login.vue
│   │   ├── MainLayout.vue
│   │   └── ToastContainer.vue
│   ├── router/          # Vue Router configuration
│   ├── services/        # API and business logic services
│   │   ├── api.js
│   │   └── authService.js
│   ├── stores/          # Pinia stores
│   │   ├── auth.js
│   │   └── toast.js
│   ├── views/           # Page components
│   │   ├── BackendCheck.vue
│   │   └── Dashboard.vue
│   ├── App.vue
│   └── main.js
├── index.html
├── package.json
└── vite.config.js
```

## Authentication

The app uses token-based authentication. After successful login, the token is stored in localStorage and automatically included in API requests via axios interceptors.

## Backend Integration

The frontend communicates with the backend API. Make sure the backend is running and accessible at the configured API base URL (default: `http://localhost:8000`).

The app automatically checks backend availability on startup via the `/ping` endpoint.
