# Выбор php
FROM php:8.4-cli

# Подготовка системы
RUN apt-get update && apt-get install -y libzip-dev libpq-dev

# Установка расширений
RUN docker-php-ext-install zip pdo pdo_pgsql

# Установка composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

# Создание рабочей директории
WORKDIR /app

# Копирование кода
COPY . .

# Установка зависимостей
RUN composer install

# Запуск приложения
CMD ["bash", "-c", "make start"]