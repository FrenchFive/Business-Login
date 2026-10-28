# Runs the demo with PHP's built-in web server — perfect for trying it out,
# not meant for production traffic (use nginx/Apache + PHP-FPM for that).
FROM php:8.3-cli-alpine

# pdo_sqlite ships with the image; pdo_mysql is needed for DB_CONNECTION=mysql.
RUN docker-php-ext-install pdo_mysql

WORKDIR /app
COPY . .

# Prepare the default SQLite database (MySQL is installed on first request instead).
RUN php bin/setup.php --fresh \
    && chown -R www-data:www-data storage

USER www-data
EXPOSE 8000

ENV PHP_CLI_SERVER_WORKERS=4
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
