FROM php:8.2-apache

# Install dependencies required by Moodle
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    curl \
    unzip \
    postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install -j$(nproc) pgsql pdo_pgsql \
    && docker-php-ext-install -j$(nproc) intl zip xml soap opcache \
    && docker-php-ext-install -j$(nproc) exif

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure PHP for Moodle via php.ini overrides
RUN echo "max_execution_time = 3600" > /usr/local/etc/php/conf.d/moodle.ini \
    && echo "memory_limit = 1024M" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "max_input_vars = 5000" >> /usr/local/etc/php/conf.d/moodle.ini

# Set DocumentRoot to public folder for headless Moodle proxy
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set working directory
WORKDIR /var/www/html

# Copy the server directory (current context should be server/)
COPY . /var/www/html/

# Copy entrypoint
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create moodledata OUTSIDE /tmp — /tmp is a tmpfs on Render and gets wiped at runtime
RUN mkdir -p /var/moodledata && chown -R www-data:www-data /var/moodledata
RUN chown -R www-data:www-data /var/www/html

# Bake the env var so PHP getenv() and bash both always agree on the path
ENV MOODLE_DATA_DIR=/var/moodledata

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
