* PHP

This docker container supports multiple PHP versions. In order to switch to another version, copy .env.dist to .env and
change PHP_VERSION to desired version. After that run a `composer update` and e.g. execute phpunit to run tests.

* Composer

There is a composer installed within the container. If you want to use it, just call e.g.
```bash
docker compose run --build php /usr/bin/composer update
```
Of course the `--build` is not necessary everytime.

* phpunit

A call to composer with update or install, will make phpunit available in its default location. To execute it, run
```bash
docker compose run --build php /web/vendor/phpunit/phpunit/phpunit
```
Of course the `--build` is not necessary everytime.
