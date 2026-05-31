# POS Enterprise

> Full-stack Point of Sale system — **Web** (Docker) + **Desktop** (Tauri 2)
> Laravel 12 · PHP 8.4 · React 19 · TypeScript · Vite · Zustand · TanStack Query · SQLite (offline)

---

## Architecture

```
pos-enterprise/
├── backend/          Laravel 12 API (PHP 8.4)
│   ├── app/          Controllers, Models, Services, DTOs, Policies
│   ├── routes/       api.php, web.php
│   ├── config/       cors.php (Tauri-ready), sanctum.php ...
│   └── Dockerfile
│
├── frontend/         React 19 + TypeScript (Web)
│   ├── src/
│   │   ├── components/  layout/, pos/, common/ ...
│   │   ├── pages/       Dashboard, POS, Products, ...
│   │   ├── stores/      authStore, cartStore, offlineStore, uiStore
│   │   ├── services/    api.ts (Axios + interceptors)
│   │   └── types/       index.ts (full type system)
│   ├── vite.config.ts
│   └── tailwind.config.js
│
├── desktop/          Tauri 2 wrapper (Desktop)
│   ├── src-tauri/
│   │   ├── src/     main.rs, lib.rs (custom commands)
│   │   ├── migrations/  001_initial.sql (SQLite offline schema)
│   │   └── tauri.conf.json
│   └── package.json
│
├── docker-compose.yml   (backend + frontend + mysql + redis)
├── .env.example
└── setup.sh             (automated setup)
```

---

## Quick Start (Web — Docker)

```bash
# 1. Clone / extract the project
cd pos-enterprise

# 2. Run the automated setup (generates .env, builds images, migrates DB)
chmod +x setup.sh
./setup.sh

# 3. Open in browser
#    Frontend:  http://localhost:5173
#    API:       http://localhost:8000/api
#    Login:     admin@example.com / password
```

That's it. The script handles everything.

---

## Quick Start (Desktop — Tauri)

### Prerequisites
- Node.js >= 18
- Rust toolchain: `curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh`
- Backend running (Docker or local)

### Linux extra deps
```bash
sudo apt-get install -y \
  libwebkit2gtk-4.1-dev libappindicator3-dev \
  librsvg2-dev patchelf libxdo-dev
```

### macOS extra deps
```bash
xcode-select --install
```

### Run desktop dev mode
```bash
./setup.sh --desktop   # first time: installs everything

# Then for daily dev:
cd desktop
npm run dev            # launches Tauri window + Vite hot-reload
```

### Build desktop installer
```bash
cd desktop
npm run build
# Outputs: desktop/src-tauri/target/release/bundle/
#   macOS  → .dmg
#   Windows → .msi / .exe
#   Linux  → .AppImage / .deb
```

---

## Development Commands

### Backend
```bash
# Shell into backend container
docker compose exec backend bash

# Common artisan commands
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
docker compose exec backend php artisan make:controller FooController --api
docker compose exec backend php artisan test
docker compose exec backend php artisan queue:work

# Tail logs
docker compose logs -f backend
docker compose logs -f queue
```

### Frontend (Web)
```bash
cd frontend
npm install
npm run dev          # http://localhost:5173
npm run build        # production build → frontend/dist/
npm run type-check   # TypeScript validation
npm run lint
npm test
```

### Desktop
```bash
cd desktop
npm run dev          # dev mode (requires Rust + Tauri CLI)
npm run build        # production bundle
```

### Docker
```bash
docker compose up -d              # start all
docker compose down               # stop all
docker compose down -v            # stop + delete volumes
docker compose build --no-cache   # rebuild images
docker compose ps                 # service status
```

---

## Environment Variables

| Variable | Description | Default |
|---|---|---|
| `DB_PASSWORD` | MySQL app user password | auto-generated |
| `MYSQL_ROOT_PASSWORD` | MySQL root password | auto-generated |
| `REDIS_PASSWORD` | Redis password | auto-generated |
| `APP_KEY` | Laravel encryption key | auto-generated |
| `VITE_API_URL` | Backend URL for Tauri desktop | `http://localhost:8000` |

---

## API Authentication

The backend uses **Laravel Sanctum** — supports both:
- **Cookie-based** (web browser SPA) — auto via `withCredentials: true`
- **Bearer token** (desktop app / mobile) — returned on `/login`, stored in Zustand

```typescript
// Login
const res = await api.post('/login', { email, password })
const { user, token } = res.data
useAuthStore.getState().login(user, token)

// All subsequent requests automatically include Authorization header
// via the Axios request interceptor in src/services/api.ts
```

---

## Offline Mode

When the device loses connectivity:
1. `offlineStore` detects `navigator.onLine = false`
2. The Axios interceptor catches `ERR_NETWORK` and sets `isOnline = false`
3. POS sales are queued in `syncQueue` (persisted to localStorage)
4. On Tauri desktop: also saved to **SQLite** via `tauri-plugin-sql`
5. When back online: the sync queue is replayed against the backend

---

## Extending the Frontend

### Add a new page
```bash
# 1. Create the page
touch frontend/src/pages/MyNewPage.tsx

# 2. Add to router in App.tsx
<Route path="my-new" element={<MyNewPage />} />

# 3. Add to sidebar nav in Sidebar.tsx
{ label: 'My New', path: '/my-new', icon: SomeIcon }
```

### Add a new API hook
```typescript
// src/hooks/useMyData.ts
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'

export function useMyData(id: number) {
  return useQuery({
    queryKey: ['my-data', id],
    queryFn: () => apiGet(`/api/my-endpoint/${id}`),
  })
}
```

---

## Production Deployment

### Web
```bash
# Build frontend
cd frontend && npm run build   # outputs to frontend/dist/

# Set backend ENV
APP_ENV=production
APP_DEBUG=false

# Build production Docker image
docker compose -f docker-compose.prod.yml up -d
```

### Desktop Installer
```bash
cd desktop
npm run build
# Sign the bundle with your code-signing cert for distribution
```

---

## Tech Stack Summary

| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.4, Sanctum, Queues, Events |
| Database | MySQL 8 (online), SQLite (offline/desktop) |
| Cache/Queue | Redis 7 |
| Frontend | React 19, TypeScript, Vite 6 |
| State | Zustand 5 + Immer |
| Data fetching | TanStack Query v5 |
| Styling | Tailwind CSS 3 |
| Forms | React Hook Form + Zod |
| Charts | Recharts |
| Desktop | Tauri 2 (Rust) |
| Container | Docker + Docker Compose |
