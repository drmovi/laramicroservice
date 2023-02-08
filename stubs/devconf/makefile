
phpstan:
	@rm -rf ./app/vendor
	@./vendor/bin/phpstan analyse --memory-limit=2G --configuration=devconf/phpstan.neon
	@ln -s ./../vendor ./app


phpstan-baseline:
	@rm -rf ./app/vendor
	@./vendor/bin/phpstan analyse --memory-limit=2G --configuration=devconf/phpstan.neon --allow-empty-baseline --generate-baseline=devconf/phpstan-baseline.neon
	@rm -rf ./app/vendor

psalm:
	@rm -rf ./app/vendor
	@./vendor/bin/psalm --config=./devconf/psalm.xml --use-baseline=psalm-baseline.xml --no-cache
	@rm -rf ./app/vendor

psalm-baseline:
	@rm -rf ./app/vendor
	@./vendor/bin/psalm --config=./devconf/psalm.xml --set-baseline=psalm-baseline.xml --no-cache
	@rm -rf ./app/vendor

test:
	@./vendor/bin/phpunit --configuration ./phpunit.xml

test-with-clover-coverage:
	@php -dxdebug.mode=coverage ./vendor/bin/phpunit --configuration ./phpunit.xml --coverage-clover ./coverage/clover.xml

test-with-html-coverage:
	@php -dxdebug.mode=coverage ./vendor/bin/phpunit --configuration ./phpunit.xml --coverage-html ./coverage

monorepo:
	@./vendor/bin/dmg monorepo:$(filter-out $@,$(MAKECMDGOALS))

style-fix:
	@{{FRAMEWORK_STYLE_FIX_COMMAND}}


style-check:
	@{{FRAMEWORK_STYLE_CHECK_COMMAND}}


lint: style-test phpstan psalm

pipeline: lint test test-with-clover-coverage

dev:
	@minikube start && skaffold dev -p local --kube-context minikube


{{FRAMEWORK_MAKEFILE_COMMANDS}}
