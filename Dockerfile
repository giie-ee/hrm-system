FROM php:8.3-apache

RUN docker-php-ext-install mysqli

COPY hrms/ /var/www/html/
COPY docker/start-apache.sh /usr/local/bin/start-apache

RUN chmod +x /usr/local/bin/start-apache

EXPOSE 10000

CMD ["/usr/local/bin/start-apache"]
