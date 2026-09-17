#!/usr/bin/env bash
# Deja Silvestre listo para verlo dentro de un Codespace: instala todo, arma la
# base de datos con los negocios de prueba y compila el frontend.
set -e

composer install --no-interaction --prefer-dist
[ -f .env ] || cp .env.example .env
php artisan key:generate
php artisan storage:link

npm install
npm run build

# Datos de prueba: 9 negocios con fotos, videos, historias y reseñas.
# Las fotos se bajan de Wikimedia Commons, así que tarda un par de minutos.
php artisan migrate:fresh --seed --force

echo ""
echo "Silvestre está listo. Abre el puerto 8000 y entra con:"
echo "  ana@test.gt   (clienta)        secret123"
echo "  kari@test.gt  (negocio Pro)    secret123"
echo "  admin@test.gt (administración) secret123"
