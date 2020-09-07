Catlab Events
=============

Setup
-----
Run ```composer install``` to download all required php libraries. Copy ```.env.example``` to ```.env``` and fill in the database
credentials. Finally, run ```php artisan migrate``` to initialize the database.

Run ```npm install``` to install all dependencies and then run ```npm run production``` to compile the resources.

You should now be able to register an account on the website.

Deploy scripts
--------------
There are two buildscripts in /build that you might want to use to deploy on production servers.

We run ```prepare.sh``` on our buildserver, then push the whole project over sftp and finally run ```upgrade.sh``` on 
the production server. There are cleaner ways to handle deploys, so feel free to use your own system.
