FROM node:22-alpine AS frontend-build

WORKDIR /frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
ARG VITE_API_BASE_URL=
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}
RUN npm run build

FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql pdo_mysql \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

WORKDIR /app
COPY hrms/ /var/www/html/
COPY --from=frontend-build /frontend/dist/ /var/www/html/
COPY bin/ ./bin/
COPY database/ ./database/
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/start-apache.sh /usr/local/bin/start-apache

RUN sed -i 's/\r$//' /usr/local/bin/start-apache \
    && chmod +x /usr/local/bin/start-apache

ENV APP_ENV=production \
    DB_CONNECTION=pgsql \
    HRMS_BACKEND_ROOT=/var/www/html \
    MIGRATE_ON_START=1 \
    SEED_ADMIN_ON_START=0 \
    SEED_DASHBOARD_USERS_ON_START=0 \
    PORT=10000

EXPOSE 10000

CMD ["/usr/local/bin/start-apache"]
