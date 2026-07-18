#!/usr/bin/env bash
#
# deploy.sh — Deploy de produção do projeto Frotika
# Uso:  ./deploy.sh
#
# Encadeia: git pull -> composer -> build front -> migrate -> caches -> restart supervisor
# Para a execução ao primeiro erro e avisa o que falhou.

set -euo pipefail

# ─── Configuração ───────────────────────────────────────────────
PROJECT_DIR="/var/www/html/frotika.com.br"
WEB_USER="www-data"
BRANCH="main"                      # ajuste se o branch de produção for outro
PHP_BIN="php"
PHP_FPM_SERVICE="php8.3-fpm"       # serviço a reiniciar para limpar o OPcache

# ─── Cores para o output ────────────────────────────────────────
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
step() { echo -e "\n${GREEN}==>${NC} $1"; }
warn() { echo -e "${YELLOW}!${NC} $1"; }
fail() { echo -e "${RED}✗ Falhou:${NC} $1"; }

# Se qualquer comando falhar, tira a aplicação do modo manutenção antes de sair
trap 'fail "deploy interrompido"; cd "$PROJECT_DIR" && $PHP_BIN artisan up || true' ERR

cd "$PROJECT_DIR"

# ─── 1. Ativa modo de manutenção ────────────────────────────────
step "Ativando modo de manutenção"
$PHP_BIN artisan down --retry=15 || true

# ─── 2. Atualiza o código ───────────────────────────────────────
step "Baixando código (git pull)"
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"   # descarta alterações locais e alinha com o remoto

# ─── 3. Dependências PHP ────────────────────────────────────────
step "Instalando dependências PHP (composer)"
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# ─── 4. Dependências e build do front (Vite/Tailwind) ───────────
step "Buildando assets do front-end"
npm ci
npm run build

# ─── 5. Migrations ──────────────────────────────────────────────
step "Rodando migrations"
$PHP_BIN artisan migrate --force

# ─── 6. Limpa e recria os caches de produção ────────────────────
# 'optimize' recria config + route + event caches de uma vez.
# 'view:cache' é adicional (o optimize não cobre as views).
step "Recriando caches de produção (artisan optimize)"
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan optimize
$PHP_BIN artisan view:cache

# ─── 7. Storage link (idempotente) ──────────────────────────────
step "Garantindo o storage link"
$PHP_BIN artisan storage:link || true

# ─── 8. Ajusta permissões ───────────────────────────────────────
step "Ajustando dono dos arquivos para $WEB_USER"
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache public/build

# ─── 9. Reinicia PHP-FPM (limpa o OPcache) e os processos do Supervisor
# O OPcache guarda o bytecode em memória; sem reiniciar o FPM, o código novo
# no disco continua sendo servido com a versão antiga em cache.
step "Reiniciando PHP-FPM (limpa o OPcache)"
systemctl restart "$PHP_FPM_SERVICE"

# Reinicia só os programas da frotika, sem tocar nos outros projetos do servidor.
step "Reiniciando workers e Reverb (Supervisor)"
supervisorctl restart frotika-queue: frotika-reverb

# ─── 10. Sai do modo de manutenção ──────────────────────────────
step "Desativando modo de manutenção"
$PHP_BIN artisan up

# Remove o trap de erro — chegou ao fim com sucesso
trap - ERR

echo -e "\n${GREEN}✓ Deploy concluído com sucesso.${NC}"
