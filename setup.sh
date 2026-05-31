#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  POS Enterprise — Complete Setup Script
#  Supports: Web (Docker) + Desktop (Tauri) modes
#
#  Usage:
#    ./setup.sh           → Full Docker setup (web mode)
#    ./setup.sh --desktop → Also prepares Tauri desktop build
#    ./setup.sh --dev     → Local dev without Docker
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

# ─── Colors ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

info()    { echo -e "${CYAN}[INFO]${RESET}  $*"; }
success() { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
error()   { echo -e "${RED}[ERROR]${RESET} $*"; exit 1; }
step()    { echo -e "\n${BOLD}${BLUE}▶ $*${RESET}"; }

# ─── Parse flags ─────────────────────────────────────────────────────────────
DESKTOP=false
DEV_MODE=false
for arg in "$@"; do
  case $arg in
    --desktop) DESKTOP=true ;;
    --dev)     DEV_MODE=true ;;
  esac
done

echo -e "${BOLD}"
echo "  ╔═══════════════════════════════════════╗"
echo "  ║      POS Enterprise Setup v1.0        ║"
echo "  ║  Web + Desktop  ·  Laravel + React    ║"
echo "  ╚═══════════════════════════════════════╝"
echo -e "${RESET}"

# ─── Step 0: Prerequisites check ─────────────────────────────────────────────
step "Checking prerequisites"

check_cmd() {
  if ! command -v "$1" &>/dev/null; then
    warn "$1 not found — $2"
    return 1
  fi
  success "$1 found"
  return 0
}

DOCKER_OK=true
NODE_OK=true
RUST_OK=true

check_cmd docker    "install from https://docker.com"           || DOCKER_OK=false
check_cmd docker    "" && docker compose version &>/dev/null || check_cmd "docker-compose" "use docker-compose v2" || DOCKER_OK=false
check_cmd node      "install from https://nodejs.org"           || NODE_OK=false
check_cmd npm       "comes with Node.js"                         || NODE_OK=false

if $DESKTOP; then
  check_cmd rustup  "install from https://rustup.rs"            || RUST_OK=false
  check_cmd cargo   "install from https://rustup.rs"            || RUST_OK=false
fi

if ! $DOCKER_OK && ! $DEV_MODE; then
  error "Docker is required for the default setup. Use --dev for local mode or install Docker."
fi
if ! $NODE_OK; then
  error "Node.js >= 18 is required. Install from https://nodejs.org"
fi

# ─── Step 1: Environment file ─────────────────────────────────────────────────
step "Setting up environment"

if [ ! -f ".env" ]; then
  cp .env.example .env
  info "Created .env from .env.example"

  # Generate random passwords
  DB_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 20)
  REDIS_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 20)
  ROOT_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 20)

  sed -i.bak \
    -e "s/your_strong_db_password_here/${DB_PASS}/g" \
    -e "s/your_strong_redis_password_here/${REDIS_PASS}/g" \
    -e "s/your_strong_root_password_here/${ROOT_PASS}/g" \
    .env && rm -f .env.bak

  success "Generated secure random passwords in .env"
else
  success ".env already exists"
fi

# Source env vars
if [ -f ".env" ]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

# ─── Step 2: Backend .env setup ───────────────────────────────────────────────
step "Configuring backend"

BACKEND_ENV="backend/.env"
if [ ! -f "$BACKEND_ENV" ]; then
  cp backend/.env.example "$BACKEND_ENV" 2>/dev/null || cp .env.example "$BACKEND_ENV"
  info "Created backend/.env"
fi

# Patch backend .env with docker service names and passwords
patch_env() {
  local file=$1 key=$2 val=$3
  if grep -q "^${key}=" "$file"; then
    sed -i.bak "s|^${key}=.*|${key}=${val}|g" "$file" && rm -f "${file}.bak"
  else
    echo "${key}=${val}" >> "$file"
  fi
}

patch_env "$BACKEND_ENV" DB_HOST          "mysql"
patch_env "$BACKEND_ENV" DB_DATABASE      "pos_system"
patch_env "$BACKEND_ENV" DB_USERNAME      "pos_user"
patch_env "$BACKEND_ENV" DB_PASSWORD      "${DB_PASSWORD:-secret}"
patch_env "$BACKEND_ENV" REDIS_HOST       "redis"
patch_env "$BACKEND_ENV" REDIS_PASSWORD   "${REDIS_PASSWORD:-redissecret}"
patch_env "$BACKEND_ENV" QUEUE_CONNECTION "redis"
patch_env "$BACKEND_ENV" CACHE_STORE      "redis"
patch_env "$BACKEND_ENV" SESSION_DRIVER   "redis"
patch_env "$BACKEND_ENV" SANCTUM_STATEFUL_DOMAINS "localhost:5173,localhost:4173,tauri://localhost"
success "Backend .env configured"

# ─── Step 3: Build & start Docker services ────────────────────────────────────
if ! $DEV_MODE; then
  step "Building Docker images (this may take a few minutes…)"
  docker compose build --parallel
  success "Docker images built"

  step "Starting services"
  docker compose up -d mysql redis
  info "Waiting for MySQL to be healthy…"
  sleep 5

  # Wait for MySQL health
  MAX_WAIT=60
  WAITED=0
  until docker compose exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
    sleep 2
    WAITED=$((WAITED + 2))
    [ $WAITED -ge $MAX_WAIT ] && error "MySQL failed to start within ${MAX_WAIT}s"
    echo -n "."
  done
  echo ""
  success "MySQL is ready"

  # ─── Step 4: Laravel setup ────────────────────────────────────────────────
  step "Setting up Laravel backend"

  ARTISAN="docker compose exec -T backend php artisan"

  # Generate app key if not set
  if ! grep -q "APP_KEY=base64:" "$BACKEND_ENV"; then
    docker compose up -d backend
    sleep 3
    $ARTISAN key:generate --force
    success "App key generated"
  fi

  docker compose up -d backend
  sleep 5

  info "Running database migrations…"
  $ARTISAN migrate --force

  info "Seeding database…"
  $ARTISAN db:seed --force 2>/dev/null || warn "Seeder skipped (no seeder or already seeded)"

  info "Caching config…"
  $ARTISAN config:cache
  $ARTISAN route:cache
  $ARTISAN view:cache

  info "Creating storage link…"
  $ARTISAN storage:link 2>/dev/null || true

  success "Laravel backend ready"
fi

# ─── Step 5: Frontend dependencies ────────────────────────────────────────────
step "Installing frontend dependencies"

cd frontend
if [ ! -d "node_modules" ]; then
  npm install
  success "Frontend packages installed"
else
  npm install --prefer-offline 2>/dev/null || npm install
  success "Frontend packages up to date"
fi
cd ..

# ─── Step 6: Desktop / Tauri setup ────────────────────────────────────────────
if $DESKTOP; then
  step "Setting up Tauri desktop app"

  if ! $RUST_OK; then
    error "Rust/Cargo required for desktop build. Install from https://rustup.rs"
  fi

  # Install Tauri CLI
  if ! command -v tauri &>/dev/null; then
    info "Installing Tauri CLI…"
    cargo install tauri-cli --version "^2" --locked
  fi

  cd desktop
  npm install
  success "Desktop dependencies installed"
  cd ..

  # Install OS-level deps for Tauri
  OS=$(uname -s)
  if [ "$OS" = "Linux" ]; then
    if command -v apt-get &>/dev/null; then
      info "Installing Linux Tauri dependencies…"
      sudo apt-get update -q
      sudo apt-get install -y -q \
        libwebkit2gtk-4.1-dev \
        libappindicator3-dev \
        librsvg2-dev \
        patchelf \
        libxdo-dev
      success "Linux Tauri deps installed"
    fi
  fi
fi

# ─── Step 7: Start all services ───────────────────────────────────────────────
if ! $DEV_MODE; then
  step "Starting all services"
  docker compose up -d
  success "All services started"
fi

# ─── Done! ────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}${BOLD}═══════════════════════════════════════════════════"
echo -e "  ✅  POS Enterprise setup complete!"
echo -e "═══════════════════════════════════════════════════${RESET}"
echo ""
echo -e "${BOLD}Access URLs:${RESET}"
echo -e "  🌐 Frontend (Web):  ${CYAN}http://localhost:5173${RESET}"
echo -e "  🔧 Backend API:     ${CYAN}http://localhost:8000/api${RESET}"
if docker compose ps 2>/dev/null | grep -q phpmyadmin; then
  echo -e "  🗄️  phpMyAdmin:     ${CYAN}http://localhost:8080${RESET}"
fi
echo ""
echo -e "${BOLD}Commands:${RESET}"
echo -e "  Start all:         ${YELLOW}docker compose up -d${RESET}"
echo -e "  Stop all:          ${YELLOW}docker compose down${RESET}"
echo -e "  Backend logs:      ${YELLOW}docker compose logs -f backend${RESET}"
echo -e "  Frontend dev:      ${YELLOW}cd frontend && npm run dev${RESET}"
if $DESKTOP; then
  echo -e "  Desktop dev:       ${YELLOW}cd desktop && npm run dev${RESET}"
  echo -e "  Desktop build:     ${YELLOW}cd desktop && npm run build${RESET}"
fi
echo -e "  Run tests:         ${YELLOW}docker compose exec backend php artisan test${RESET}"
echo ""
echo -e "${BOLD}Default credentials (seeded):${RESET}"
echo -e "  Email:    admin@example.com"
echo -e "  Password: password"
echo ""
