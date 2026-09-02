# syntax=docker/dockerfile:1
FROM 8.1.32-apache
# TODO: look into updating to more recent PHP

COPY ./src /var/www/html
COPY config/config.py /src/config.py

USER www-data

EXPOSE 80
CMD ["php", "-S", "0.0.0.0:8000"]