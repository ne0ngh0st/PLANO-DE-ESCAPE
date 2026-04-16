#!/bin/bash
set -e

echo "=== BI Autopel - Docker Entrypoint ==="

# Corrigir hashes de senha no banco (aguarda MySQL ficar pronto)
echo "Atualizando senhas no banco..."
php /var/www/html/docker/fix-passwords.php

echo "=== Sistema pronto! Acesse http://localhost:8080 ==="

# Iniciar Apache
exec apache2-foreground
