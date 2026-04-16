PORT ?= 8000
DATABASE_URL ?= 'postgresql://mikhaelhan:mha7X0Poc7Gv0N7k06o6tPGmdDRmvMSi@dpg-d7gaevvlk1mc7383qj5g-a.oregon-postgres.render.com/php_project_9_3vzw'

# Запуск сервера
start:
	DATABASE_URL=$(DATABASE_URL) PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public

# Установка зависимостей
install:
	composer install

# Запуск линтера
lint:
	composer lint
lint-fix:
	composer lint-fix