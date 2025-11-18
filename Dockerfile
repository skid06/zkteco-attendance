# Multi-stage build for ZKTeco Attendance Sync Application

# Stage 1: Build stage with Composer
FROM php:8.2-cli AS builder

# Install system dependencies and build tools
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    libsqlite3-dev \
    zip \
    unzip \
    autoconf \
    g++ \
    make \
    && docker-php-ext-install -j$(nproc) \
    sockets \
    zip \
    pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies (production only, no dev dependencies)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Stage 2: Production runtime
FROM php:8.2-cli

# Install runtime dependencies only
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libsqlite3-dev \
    && docker-php-ext-install -j$(nproc) \
    sockets \
    zip \
    pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy application files
COPY --chown=www-data:www-data . .

# Copy vendor from builder stage
COPY --from=builder --chown=www-data:www-data /app/vendor ./vendor

# Create necessary directories and set permissions
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache database && \
    touch database/database.sqlite && \
    chown -R www-data:www-data storage bootstrap/cache database && \
    chmod -R 775 storage bootstrap/cache database

# Switch to www-data user for security
USER www-data

# Health check to verify the application is working
HEALTHCHECK --interval=60s --timeout=10s --start-period=30s --retries=3 \
    CMD php artisan attendance:sync --test || exit 1

# Default command - run the sync command
CMD ["php", "artisan", "attendance:sync"]
