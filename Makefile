# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.

app_name=$(notdir $(CURDIR))
build_dir=$(CURDIR)/build
build_tools_dir=$(build_dir)/tools
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
cert_dir=$(HOME)/.nextcloud/certificates
composer=$(shell which composer 2> /dev/null)
translationtool_url=https://github.com/nextcloud/docker-ci/raw/master/translations/translationtool/translationtool.phar


all: dev-setup lint build test

dev-setup: distclean composer npm-init translationtool

build: build-js-production
build-dev: build-js


# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
composer:
ifeq (, $(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_dir)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_dir)
	php $(build_tools_dir)/composer.phar install --prefer-dist
	php $(build_tools_dir)/composer.phar update --prefer-dist
else
	composer install --prefer-dist
	composer update --prefer-dist
endif

npm-init:
	npm ci

# Installs nextclouds translation tool
translationtool:
ifeq (, $(wildcard $(build_tools_dir)/translationtool.phar))
	mkdir -p $(build_tools_dir)
	curl $(translationtool_url) --silent --location --output $(build_tools_dir)/translationtool.phar
endif

# Linting
lint:
	npm run lint

# Building
build-js:
	npm run dev

build-js-production:
	npm run build

# Builds translation template from source code and update 
po:
	php $(build_tools_dir)/translationtool.phar create-pot-files
	sed -i 's/^#: .*\/collectives/#: \/collectives/' $(CURDIR)/translationfiles/templates/collectives.pot
	for pofile in $(CURDIR)/translationfiles/*/collectives.po; do \
		msgmerge --backup=none --update "$$pofile" translationfiles/templates/collectives.pot; \
	done

l10n: po
	php $(build_tools_dir)/translationtool.phar convert-po-files

# Testing
test:
	$(CURDIR)/vendor/bin/phpunit --configuration phpunit.xml
	$(CURDIR)/vendor/bin/behat --config=tests/Integration/config/behat.yml

# Cleaning
clean:
	rm -rf js/*

# Same as clean but also removes dependencies installed by composer and npm
distclean: clean
	rm -rf $(build_tools_dir)
	rm -rf $(source_dir)
	rm -rf $(sign_dir)
	rm -rf node_modules
	rm -rf vendor

# Builds the source and appstore package
dist:
	make source
	make appstore

# Builds the source package
source: clean build
	rm -rf $(source_dir)
	mkdir -p $(source_dir)
	tar cvzf $(source_package_name).tar.gz ../$(app_name) \
	--exclude-vcs \
	--exclude="../$(app_name)/build" \
	--exclude="../$(app_name)/node_modules"

# Builds the source package for the app store, ignores php and js tests
appstore: clean build
	rm -rf $(sign_dir)
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude-vcs \
	--exclude="../$(app_name)/.*" \
	--exclude="../$(app_name)/Makefile" \
	--exclude="../$(app_name)/build" \
	--exclude="../$(app_name)/composer.*" \
	--exclude="../$(app_name)/package-lock.json" \
	--exclude="../$(app_name)/package.json" \
	--exclude="../$(app_name)/phpunit.xml" \
	--exclude="../$(app_name)/protractor\.*" \
	--exclude="../$(app_name)/src" \
	--exclude="../$(app_name)/tests" \
	--exclude="../$(app_name)/webpack.*" \
	./ $(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(version).t    ar.gz | openssl base64; \
	fi
