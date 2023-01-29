FROM drmovi/php8.1-swoole

ARG MICROSERVICE
ARG APP_ENV
ARG PACKAGE_NAME

RUN mkdir -p /etc/supervisor.d/ && mkdir /var/log/supervisor/
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./docker/php.ini /etc/php/8.1/cli/conf.d/99-app.ini
COPY ./docker/start /usr/local/bin/start
COPY ./docker/supervisor-config /usr/local/bin/supervisor-config
RUN chmod +x /usr/local/bin/start
RUN chmod +x /usr/local/bin/supervisor-config

WORKDIR /var/app

COPY . .

RUN chmod +x ./splitter.php

RUN php splitter.php $MICROSERVICE

RUN composer install $( if [ "$APP_ENV" != "dev" ]; then echo '--no-dev'; fi ) --no-interaction --no-progress --no-scripts --no-suggest --optimize-autoloader

ENTRYPOINT ["start"]
