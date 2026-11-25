# Local development

This guide sets up a local copy of the site with test data. It is written for a person or a coding agent who works mainly on the design and does not know PHP. All the software runs in docker containers, so you do not install PHP, Composer, Node or MySQL yourself.

## What you need

- git
- Docker with the `docker compose` plugin
- Free ports 8095 (the site), 8099 (the demo images) and 3312 (MySQL)

If port 3312 is in use, set `MYSQL_HOST_PORT` to a free port in `.env`.

### On a Mac

1. Install git. If `git --version` asks to install the command line developer tools, accept.
2. Install Docker Desktop from https://www.docker.com/products/docker-desktop/. Choose "Apple Silicon" for an M1 or later, "Intel chip" for an older Mac.
3. Open Docker Desktop and wait until it shows "Engine running".
4. In Docker Desktop, open Settings > Resources and give Docker at least 4 GB of memory.
5. Open Terminal, clone the repository and go into its folder.

Docker Desktop must run each time you work on the site. All images of this project exist for Apple Silicon, so docker does not need emulation.

On a Mac, the containers read the project folder through a file share, which is slower than on Linux. The first setup can take up to 20 minutes. Pages also load slower than on the real site.

The script runs `npm install` inside the container. Because of this, `node_modules` contains Linux files. Do not run `npm` directly on the Mac in this folder. Run it with `docker compose exec webserver npm ...`. If you run `npm install` on the Mac by mistake, run `rm -rf node_modules` and then `bin/local-setup.sh`.

To open the site from Terminal, run `open http://localhost:8095`.

## Set up

Run this one command from the root of the repository:

    bin/local-setup.sh

The script does these steps:

1. If `.env` does not exist, it creates `.env` from `.env.example` with local values.
2. It builds and starts three containers: the web server, MySQL and an image server.
3. The web server container installs the PHP packages each time it starts. The script installs the Node packages inside the same container.
4. It builds the CSS and JavaScript into `public/`.
5. It creates the database tables and loads the test data.

The first run takes 5 to 15 minutes, because docker builds the image. Later runs take less than a minute. You can run the script again at any time. It does not delete data.

When the script is done, open these addresses:

| What | Address |
| --- | --- |
| Public site | http://localhost:8095 |
| Admin panel | http://localhost:8095/admin |
| Demo images | http://localhost:8099 |

Log in with `demo@example.com` and the password `password`. This user is an admin of the demo organisation.

## The test data

The test data comes from `database/seeds/PreviewSeeder.php`. The same data feeds the PR previews on GitHub. It contains:

- One organisation, "De Quizfabriek", with a logo
- Two series, "Woonkamerquiz" and "Quizavonden"
- A quiz package and four events with ticket prices, a venue and posters in different shapes (portrait, square, panorama, no poster)
- Five people (authors, presenters, technicians) on the quiz package
- The CMS page `/quizzen-op-maat` with one block of every block type
- The CMS page `/over-ons` with plain text
- Four blog posts at `/blog`, two with an image

The event dates are relative to the day you load the data, so the events always show on the calendar. All text is demo text, not real content.

Pages to look at for design work:

| Page | Address |
| --- | --- |
| Home | http://localhost:8095/ |
| Events | http://localhost:8095/events |
| Calendar | http://localhost:8095/calendar |
| One event | open an event from the calendar |
| CMS page with all blocks | http://localhost:8095/quizzen-op-maat |
| Text page | http://localhost:8095/over-ons |
| Blog | http://localhost:8095/blog |
| Admin: events | http://localhost:8095/admin/events |

To delete all data and load the test data again, run:

    bin/local-setup.sh --fresh

The demo images are the files in `.github/preview/assets`. The `assets` container serves them in place of CentralStorage (the image storage of the real site). Images that you upload in the admin panel do not work locally, because the upload goes to CentralStorage.

## Where the design lives

| What | Where |
| --- | --- |
| Colors, fonts, spacing (design tokens) | `resources/assets/sass/_kv-tokens.scss` |
| Element defaults | `resources/assets/sass/_kv-base.scss` |
| Components of the public site (`kv-*` classes) | `resources/assets/sass/_kleurveld.scss` |
| CMS pages and blog | `resources/assets/sass/_kleurveld-cms.scss` |
| Main stylesheet of the public site | `resources/assets/sass/app.scss` |
| Admin panel styles | `resources/assets/sass/admin.scss` |
| JavaScript of the public site | `resources/assets/js/kv.js` |
| Page layouts | `resources/views/layouts/` |
| Shared parts (event card, poster, ticket table) | `resources/views/blocks/` |
| Event pages | `resources/views/events/` |
| CMS block templates (hero, cards, gallery...) | `resources/views/cms/blocks/` |
| Blog templates | `resources/views/cms/blog/` |
| Static images | `resources/assets/images/` |

The templates are Blade files (`*.blade.php`). Blade is HTML with a few tags in `{{ }}` and `@if` / `@foreach` lines. You can change the HTML around those tags without knowing PHP. A change to a template shows after a page reload.

A change to a `.scss` or `.js` file in `resources/assets` shows only after a new build. Run:

    docker compose exec webserver npm run dev

To build again on each save, keep this command open in a second terminal:

    docker compose exec webserver npm run watch-poll

To update the styles in the open page without a reload, use hot reload instead. Hot reload serves the assets on port 8080. `docker-compose.yml` publishes that port. If your containers are older than that line, run `docker compose up -d` first.

    docker compose exec webserver npm run hot

Stop it with Ctrl+C before you run `npm run dev` again. While hot reload runs, `public/mix-manifest.json` changes. Do not commit that change.

Do not commit the built files in `public/css`, `public/js` and `public/mix-manifest.json` unless the change needs them. Production builds them again during the deploy.

## Daily use

| Task | Command |
| --- | --- |
| Start the containers | `docker compose up -d` |
| Stop the containers | `docker compose stop` |
| Show the web server log | `docker compose logs -f webserver` |
| Show the application errors | `docker compose exec webserver tail -n 50 storage/logs/laravel.log` |
| Run a command in the web server | `docker compose exec webserver <command>` |
| Bring the copy up to date after `git pull` | `bin/local-setup.sh` |

If the application log has a different name, look in `storage/logs/` for the newest file.

## Show a design change to others

Name the branch `ui/<something>`, or add the label `preview` to the pull request. GitHub then builds a static preview of the public pages with the same test data. A bot puts the link in a comment on the pull request. See `.github/workflows/preview.yml`.

## Problems

If the site shows "No application encryption key has been specified", run `docker compose exec webserver php artisan key:generate` and reload.

If the login page sends you to accounts.catlab.eu, `.env` has a value for `CATLAB_CLIENT_ID`. Empty that value to use the demo login.

If images do not show, make sure that the `assets` container runs (`docker compose ps`) and that `.env` has `CENTRALSTORAGE_FRONT=http://localhost:8099`.

If the calendar shows a database error about `ONLY_FULL_GROUP_BY`, set `DB_STRICT=false` in `.env`.

If the page has no styles, run `docker compose exec webserver npm run dev`.

If a port is in use, stop the other program, or change the port in `docker-compose.yml`. For MySQL, set `MYSQL_HOST_PORT` in `.env`.

## The WordPress import

The old WordPress site is at https://oud.quizfabriek.be. The command `wordpress:import` copies its blog posts and pages into the CMS. You do not need it for design work, because the test data already has CMS pages and posts.

The import needs internet access and CentralStorage for the images, so it does not work in a plain local copy. In production, an admin runs:

    php artisan wordpress:import --organisation=<id> --dry-run

The default site is `https://oud.quizfabriek.be`. The content of that site still links to `www.quizfabriek.be`, the old address. The option `--alias=www.quizfabriek.be` (the default) makes the import treat links and images on that address as part of the site. The import always downloads the images from `oud.quizfabriek.be`. Run `php artisan help wordpress:import` for all options.
