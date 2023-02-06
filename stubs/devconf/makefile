
phpstan:
	@./vendor/bin/phpstan analyse --memory-limit=2G --configuration={{DEV_CONF_PATH}}/phpstan.neon

phpstan-baseline:
	@./vendor/bin/phpstan analyse --memory-limit=2G --configuration={{DEV_CONF_PATH}}/phpstan.neon --allow-empty-baseline --generate-baseline={{DEV_CONF_PATH}}/phpstan-baseline.neon

psalm:
	@./vendor/bin/psalm --config=./{{DEV_CONF_PATH}}/psalm.xml --update-baseline --set-baseline=psalm-baseline.xml --no-cache

psalm-baseline:
	@./vendor/bin/psalm --config=./{{DEV_CONF_PATH}}/psalm.xml --update-baseline --no-cache

test:
	@./vendor/bin/phpunit --configuration ./phpunit.xml

test-with-clover-coverage:
	@php -dxdebug.mode=coverage ./vendor/bin/phpunit --configuration ./phpunit.xml --coverage-clover ./coverage/clover.xml

test-with-html-coverage:
	@php -dxdebug.mode=coverage ./vendor/bin/phpunit --configuration ./phpunit.xml --coverage-html ./coverage

monorepo:
	@./vendor/bin/dmg $(filter-out $@,$(MAKECMDGOALS))

style-fix:


style-check:


lint: style-test phpstan psalm

pipeline: lint test test-with-clover-coverage


artisan:
	@php ./{{APP_PATH}}/artisan "$@"
