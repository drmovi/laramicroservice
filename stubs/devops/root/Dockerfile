FROM drmovi/php8.2-swoole


RUN mkdir -p /etc/supervisor.d/ && mkdir /var/log/supervisor/
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./docker/php.ini /etc/php/8.1/cli/conf.d/99-app.ini
COPY ./docker/start /usr/local/bin/start
COPY ./docker/supervisor-config /usr/local/bin/supervisor-config
COPY ./docker/composer-installer ./var/app/composer-installer
RUN chmod +x /usr/local/bin/start && chmod +x /usr/local/bin/supervisor-config && chmod +x ./var/app/composer-installer

WORKDIR /var/app

COPY . .

ARG PACKAGE_NAME
ARG APP_ENV
RUN PACKAGE_NAME=$PACKAGE_NAME APP_ENV=$APP_ENV ./composer-installer
RUN --mount=type=cache,target=/tmp/cache composer update --no-scripts --no-interaction  "$( if [ "$APP_ENV" != "local" ]; then echo '--no-dev'; fi )" --optimize-autoloader

RUN app_path=$(composer config extra.monorepo.app_path) && rm -rf ./"$app_path"/vendor &&  ln -s ./../vendor ./"$app_path"

ENTRYPOINT ["start"]
