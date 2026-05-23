FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql opcache
# proxy_ssl needed when BACKEND_PROXY_URL uses https://
RUN a2enmod rewrite proxy proxy_http proxy_ssl ssl headers deflate expires

WORKDIR /var/www/html
COPY . /var/www/html
COPY docker/apache-backend-proxy.conf /etc/apache2/conf-available/backend-proxy.conf
COPY docker/apache-security.conf /etc/apache2/conf-available/security-hardening.conf
COPY docker/apache-performance.conf /etc/apache2/conf-available/performance-tuning.conf
COPY docker/php-security.ini /usr/local/etc/php/conf.d/security.ini
RUN a2enconf backend-proxy
RUN a2enconf security-hardening
RUN a2enconf performance-tuning

EXPOSE 80
