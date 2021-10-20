# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.

# Variables that can be overridden by env variables
VERSION?=$(shell sed -ne 's/^\s*<version>\(.*\)<\/version>/\1/p' appinfo/info.xml)
GIT_TAG?=v$(VERSION)
OCC?=php ../../occ
NPM?=npm

# Release variables
VERSION_CHANGELOG:=$(shell sed -ne 's/^\#\#\s\([0-9\.]\+-*\w*\)\s-\s.*$$/\1/p' CHANGELOG.md | head -n1 )
GITLAB_GROUP:=collectivecloud
GITLAB_PROJECT:=collectives
GITLAB_PROJECT_ID:=17827012
GITLAB_URL:=https://gitlab.com
GITLAB_API_URL:=$(GITLAB_URL)/api/v4/projects/$(GITLAB_PROJECT_ID)

# Upgrade: once we have git >= 2.22 everywhere we can use the more
# readable GIT_BRANCH:=$(shell git branch --show-current)
GIT_BRANCH:=$(shell git rev-parse --abbrev-ref HEAD)
GIT_REMOTE:=$(shell git config --get "branch.${GIT_BRANCH}.remote")

# So far just for removing releases again
NEXTCLOUD_API_URL:=https://apps.nextcloud.com/api/v1/apps/collectives

# Internal variables
APP_NAME:=$(notdir $(CURDIR))
PROJECT_DIR:=$(CURDIR)/../$(APP_NAME)
BUILD_DIR:=$(CURDIR)/build
BUILD_TOOLS_DIR:=$(BUILD_DIR)/tools
RELEASE_DIR:=$(BUILD_DIR)/release
CERT_DIR:=$(HOME)/.nextcloud/certificates

# Meta targets
all: setup-dev lint build test

setup-dev: composer-install node-modules

# Install build tools
composer:
ifeq (, $(wildcard $(BUILD_TOOLS_DIR)/composer.phar))
	mkdir -p $(BUILD_TOOLS_DIR)
	cd $(BUILD_TOOLS_DIR) && curl -sS https://getcomposer.org/installer | php
endif

translationtool:
ifeq (, $(wildcard $(BUILD_TOOLS_DIR)/translationtool.phar))
	mkdir -p $(BUILD_TOOLS_DIR)
	curl https://github.com/nextcloud/docker-ci/raw/master/translations/translationtool/translationtool.phar \
		--silent --location --output $(BUILD_TOOLS_DIR)/translationtool.phar
endif

$(BUILD_TOOLS_DIR)/info.xsd:
	mkdir -p $(BUILD_TOOLS_DIR)
	curl https://apps.nextcloud.com/schema/apps/info.xsd \
	--silent --location --output $(BUILD_TOOLS_DIR)/info.xsd

# Install dependencies
node-modules:
	$(NPM) ci

composer-install: composer
	php $(BUILD_TOOLS_DIR)/composer.phar install --prefer-dist

# Clean build artifacts
clean:
	rm -rf js/*
	rm -rf $(RELEASE_DIR)/collectives

# Also remove build tools and dependencies
distclean: clean
	rm -rf $(BUILD_TOOLS_DIR)
	rm -rf node_modules
	rm -rf vendor

# Lint
lint: lint-js lint-appinfo

lint-js:
	$(NPM) run lint

lint-appinfo: $(BUILD_TOOLS_DIR)/info.xsd
	xmllint appinfo/info.xml --noout \
		--schema $(BUILD_TOOLS_DIR)/info.xsd

build-js-dev:
	$(NPM) run dev

build-js-production:
	$(NPM) run build

# Testing
test: test-php test-js

test-php: test-php-unit test-php-integration

test-php-unit:
	$(CURDIR)/vendor/bin/phpunit --configuration phpunit.xml

test-php-integration:
	$(CURDIR)/vendor/bin/behat --config=tests/Integration/config/behat.yml

test-js:
	$(NPM) test

test-js-cypress:
	cd cypress && ./runLocal.sh run

test-js-cypress-watch:
	cd cypress && ./runLocal.sh open

# Development

# Build and update translation template from source code
po: translationtool clean
	php $(BUILD_TOOLS_DIR)/translationtool.phar create-pot-files
	sed -i 's/^#: .*\/collectives/#: \/collectives/' $(CURDIR)/translationfiles/templates/collectives.pot
	for pofile in $(CURDIR)/translationfiles/*/collectives.po; do \
		msgmerge --backup=none --update "$$pofile" translationfiles/templates/collectives.pot; \
	done

# Update l10n files from translation templates
l10n: po
	php $(BUILD_TOOLS_DIR)/translationtool.phar convert-po-files

# Update psalm baseline
php-psalm-baseline:
	$(CURDIR)/vendor/bin/psalm.phar --set-baseline=tests/psalm-baseline.xml
	$(CURDIR)/vendor/bin/psalm.phar --update-baseline

text-app-includes:
	for n in `cat .files_from_text`; do cp ../../apps/text/$$n $$n ; done

# Build a release package
build: node-modules build-js-production
	mkdir -p $(RELEASE_DIR)
	rsync -a --delete --delete-excluded \
		--exclude=".[a-z]*" \
		--exclude="Makefile" \
		--exclude="Dockerfile" \
		--exclude="babel.config.js" \
		--exclude="build" \
		--exclude="composer.*" \
		--exclude="cypress" \
		--exclude="cypress.json" \
		--exclude="docs" \
		--exclude="jest.config.json" \
		--exclude="node_modules" \
		--exclude="package-lock.json" \
		--exclude="package.json" \
		--exclude="phpunit.xml" \
		--exclude="psalm.xml" \
		--exclude="src" \
		--exclude="tests" \
		--exclude="vendor" \
		--exclude="webpack.*" \
	$(PROJECT_DIR) $(RELEASE_DIR)/
	@if [ -f $(CERT_DIR)/$(APP_NAME).key ]; then \
		echo "Signing code…"; \
		$(OCC) integrity:sign-app --privateKey="$(CERT_DIR)/$(APP_NAME).key" \
			--certificate="$(CERT_DIR)/$(APP_NAME).crt" \
			--path="$(RELEASE_DIR)/$(APP_NAME)"; \
	fi
	tar -czf $(RELEASE_DIR)/$(APP_NAME)-$(VERSION).tar.gz \
		-C $(RELEASE_DIR) $(APP_NAME)
	# Sign the release tarball
	@if [ -f $(CERT_DIR)/$(APP_NAME).key ]; then \
		echo "Signing release tarball…"; \
		openssl dgst -sha512 -sign $(CERT_DIR)/$(APP_NAME).key \
			$(RELEASE_DIR)/$(APP_NAME)-$(VERSION).tar.gz | openssl base64; \
	fi
	rm -rf $(RELEASE_DIR)/collectives

release-checks:
ifneq ($(VERSION),$(VERSION_CHANGELOG))
	$(error Version missmatch between `appinfo/info.xml`: $(VERSION) and `CHANGELOG.md`: $(VERSION_CHANGELOG))
endif
ifndef GITLAB_API_TOKEN
	$(error Missing $$GITLAB_API_TOKEN)
endif
	@if git tag | grep $(GIT_TAG); then \
		echo "Git tag already exists!"; \
		echo "Delete it with 'git tag -d $(GIT_TAG)'"; \
		exit 1; \
	fi
	@if git ls-remote --tags $(GIT_REMOTE) "refs/tags/$(GIT_TAG)" | grep $(GIT_TAG); then \
		echo "Git tag already exists on remote $(GIT_REMOTE)!"; \
		echo "Delete it with 'git push $(GIT_REMOTE) :$(GIT_TAG)'"; \
		exit 1; \
	fi

# Prepare the release package for the app store
release: release-checks lint-appinfo build
	# Upload the release tarball
	$(eval UPLOAD_PATH:=$(shell curl -s -X POST -H "PRIVATE-TOKEN: $(GITLAB_API_TOKEN)" \
			--form "file=@build/release/collectives-$(VERSION).tar.gz" $(GITLAB_API_URL)/uploads | jq -r '.full_path'))

	# Git tag and push
	git tag $(GIT_TAG) -m "Version $(VERSION)" && git push $(GIT_REMOTE) $(GIT_TAG)

	# Publish the release on Gitlab
	curl -s -X POST -H "PRIVATE-TOKEN: $(GITLAB_API_TOKEN)" \
		-H "Content-Type: application/json" \
		--data "{ \"tag_name\": \"$(GIT_TAG)\", \"assets\": {\"links\": [ {\"name\": \"App Store Package\", \"url\": \"$(GITLAB_URL)$(UPLOAD_PATH)\", \"link_type\": \"package\"} ] } }" "$(GITLAB_API_URL)/releases" | jq .

	@echo "URL to release tarball (for app store): $(GITLAB_URL)$(UPLOAD_PATH)"

delete-release: delete-release-from-gitlab delete-release-from-appstore

delete-release-from-gitlab:
ifndef RELEASE_NAME
	  $(error Please specify the release to remove with $$RELEASE_NAME)
endif
ifndef GITLAB_API_TOKEN
	  $(error Missing $$GITLAB_API_TOKEN)
endif
	echo 'Removing release from gitlab.'
	curl -s -X DELETE -H "PRIVATE-TOKEN: $(GITLAB_API_TOKEN)" \
		$(GITLAB_API_URL)/releases/v$(RELEASE_NAME)

delete-release-from-appstore:
ifndef RELEASE_NAME
	  $(error Please specify the release to remove with $$RELEASE_NAME)
endif
ifndef NEXTCLOUD_PASSWORD
	  $(error Missing $$NEXTCLOUD_PASSWORD)
endif
	echo 'Removing release from nextcloud app store.'
	curl -s -X DELETE $(NEXTCLOUD_API_URL)/releases/$(RELEASE_NAME) \
		-u 'collectivecloud:$(NEXTCLOUD_PASSWORD)'

.PHONY: all setup-dev composer translationtool node-modules composer-install clean distclean lint lint-js lint-appinfo build build-js-dev build-js-production test test-php test-php-unit test-php-integration test-js test-js-cypress test-js-cypress-watch po l10n php-psalm-baseline text-app-includes build release delete-release delete-release-from-gitlab delete-release-from-appstore
