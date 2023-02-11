FROM composer:2.5.2 AS composer

ARG PACKAGE_NAME
ARG APP_ENV

WORKDIR /var/app

COPY . .

RUN --mount=type=cache,target=/tmp/cache composer install --no-scripts --no-interaction  $( if [ "$APP_ENV" != "local" ]; then echo '--no-dev'; fi ) --optimize-autoloader

RUN chmod +x ./microservice

RUN PACKAGE_NAME=$PACKAGE_NAME APP_ENV=$APP_ENV ./microservice

RUN rm -rf ./microservice && rm -rf ./docker && rm -rf ./devconf


FROM drmovi/php8.2-swoole

RUN mkdir -p /etc/supervisor.d/ && mkdir /var/log/supervisor/
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./docker/php.ini /etc/php/8.1/cli/conf.d/99-app.ini
COPY ./docker/start /usr/local/bin/start
COPY ./docker/supervisor-config /usr/local/bin/supervisor-config
RUN chmod +x /usr/local/bin/start && chmod +x /usr/local/bin/supervisor-config

WORKDIR /var/app


COPY --from=composer /var/app /var/app

RUN app_path=$(composer config extra.monorepo.app_path) && rm -rf ./"$app_path"/vendor &&  ln -s ./../vendor ./"$app_path"

ENTRYPOINT ["start"]
