app_name=gpxpod
app_version=$(version)
project_dir=$(CURDIR)/../$(app_name)
build_dir=/tmp/build
sign_dir=/tmp/sign
cert_dir=$(HOME)/.nextcloud/certificates

all: appstore

clean:
	rm -rf $(build_dir)
	rm -rf $(sign_dir)

appstore: clean
	mkdir -p $(sign_dir)
	mkdir -p $(build_dir)
	rsync -a \
	--exclude=.git \
	--exclude=*.swp \
	--exclude=build \
	--exclude=.gitignore \
	--exclude=.travis.yml \
	--exclude=.scrutinizer.yml \
        --exclude=CONTRIBUTING.md \
	--exclude=composer.json \
	--exclude=composer.lock \
	--exclude=composer.phar \
	--exclude=l10n/.tx \
	--exclude=l10n/no-php \
	--exclude=makefile \
	--exclude=screenshots \
	--exclude=phpunit*xml \
	--exclude=tests \
	--exclude=vendor/bin \
	$(project_dir) $(sign_dir)
	tar -czf $(build_dir)/$(app_name)-$(app_version).tar.gz \
		-C $(sign_dir) $(app_name)
	openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(app_version).tar.gz | openssl base64
