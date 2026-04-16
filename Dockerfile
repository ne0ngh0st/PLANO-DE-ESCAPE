FROM php:8.1-apache

# Instalar extensões PHP necessárias
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
        zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite para .htaccess
RUN a2enmod rewrite

# Configurar Apache para permitir .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Configurar PHP para desenvolvimento (mostrar erros)
RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

# Copiar código do projeto
COPY . /var/www/html/

# Tornar entrypoint executável
RUN chmod +x /var/www/html/docker/docker-entrypoint.sh

# Ajustar permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker/docker-entrypoint.sh"]
