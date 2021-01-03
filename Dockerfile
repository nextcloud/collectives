FROM nextcloudci/server:server-17

ENV BRANCH=stable20
RUN /usr/local/bin/initnc.sh
COPY --chown=www-data:www-data . /var/www/html/apps/collectives
RUN bash /var/www/html/apps/collectives/cypress/server.sh


ENTRYPOINT /usr/local/bin/run.sh

# FROM nextcloud:18-rc-apache


#ENTRYPOINT []
#CMD ["apache2-foreground"]
