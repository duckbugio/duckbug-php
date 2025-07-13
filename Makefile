init: docker-down-clear \
	docker-pull docker-build docker-up \
	composer-install

up: docker-up
down: docker-down
restart: down up

docker-up:
	docker compose up -d

docker-down:
	docker compose down --remove-orphans

docker-down-clear:
	docker compose down -v --remove-orphans

docker-pull:
	docker compose pull

docker-build:
	docker compose build --pull

composer-install:
	docker compose run --rm php-cli composer install

composer-update:
	docker compose run --rm php-cli composer update


check: lint analyze test bench

lint:
	docker compose run --rm php-cli composer lint
	docker compose run --rm php-cli composer php-cs-fixer fix -- --dry-run --diff

lint-fix:
	docker compose run --rm php-cli composer php-cs-fixer fix

analyze:
	docker compose run --rm php-cli composer psalm

test:
	docker compose run --rm php-cli composer test

bench:
	docker compose run --rm php-cli composer phpbench
