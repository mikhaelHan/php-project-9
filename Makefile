PORT ?= 8000

# Запуск сервера
start:
	PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public

# Установка зависимостей
install:
	composer install

# Запуск линтера
lint:
	composer lint
lint-fix:
	composer lint-fix