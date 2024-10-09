FROM php:7.2-apache

COPY configs/000-default.conf /etc/apache2/sites-available

COPY source/ /var/www/html/

RUN a2enmod rewrite