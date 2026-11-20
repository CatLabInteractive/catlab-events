# catlab-events

Laravel ticketing site for quiz events, with a CMS (pages and blog) for the organisation's public site. Production is PHP 8.5 in the Docker image of `Dockerfile`, deployed with dokku.

## Local copy with test data

Read `docs/local-development.md` first. Short version, it needs only docker:

```sh
bin/local-setup.sh          # set up, or bring up to date after git pull
bin/local-setup.sh --fresh  # wipe the database and load the test data again
```

Then the site is at http://localhost:8095. Log in at http://localhost:8095/admin with `demo@example.com` / `password`. The test data is `database/seeds/PreviewSeeder.php`.

Run PHP, Composer, npm and artisan commands in the web server container, for example `docker compose exec webserver php artisan route:list`. The host does not need PHP or Node.

On a Mac, Docker Desktop must run before you start. Do not run `npm` on the Mac itself: `node_modules` holds Linux files from the container. The section "On a Mac" in `docs/local-development.md` has the details.

## Design work

The public site's styles are in `resources/assets/sass/` (`_kv-tokens.scss` holds the colors and fonts). The templates are in `resources/views/`. The table "Where the design lives" in `docs/local-development.md` maps each part of the site to its files.

After a change in `resources/assets`, rebuild with `docker compose exec webserver npm run dev`, then reload the page. Template changes need only a reload.

To see a change, open the page in a browser at http://localhost:8095 and take a screenshot. The pages in the "Pages to look at" table of `docs/local-development.md` cover the main layouts.

## Tests

```sh
docker compose exec webserver vendor/bin/phpunit --testsuite Unit
bin/integration-tests.sh
```

## Git

Work on a feature branch and open a pull request against `master`. Do not push to `master`. A branch named `ui/<something>` gets a static preview of the public pages on GitHub (see `.github/workflows/preview.yml`).
