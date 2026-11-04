# Imagem de produção da Agenda Estética para o Render
# PHP 8.3 + Apache, servindo o conteúdo testado de deploy-htdocs.
FROM php:8.3-apache

# Código da aplicação (front controller na raiz, src/, data/, assets/)
COPY deploy-htdocs/ /var/www/html/

# Render envia tráfego ao container na porta 10000 — ajusta o Apache a isso
RUN echo "Listen 10000" > /etc/apache2/ports.conf \
    && sed -i 's/:80/:10000/' /etc/apache2/sites-enabled/000-default.conf \
    && docker-php-ext-install opcache > /dev/null

# Banco JSON precisa de escrita pelo usuário do Apache
RUN chown -R www-data:www-data /var/www/html/data

EXPOSE 10000
