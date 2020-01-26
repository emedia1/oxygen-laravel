# OxygenProject

## Deployment Instructions

```
// update the code to latest master branch
git pull origin master

// create a local php alias to the correct version
alias php=/opt/cpanel/ea-php71/root/usr/bin/php

// install with composer
/opt/cpanel/ea-php71/root/usr/bin/php /opt/cpanel/composer/bin/composer install --no-dev
```

## Deployment Server Initial Setup Instructions

```
// copy the .env file
cp .env.example .env

// edit the .env file with the server settings

// link the storage folder
php artisan storage:link

// generate app key
php artisan key:generate
```

### Development Instructions

Migrate and seed the database
```
php artisan db:refresh
```

Run the local development watcher
```
npm run watch
```

Generate API documentation
```
php artisan generate:docs && apidoc -i resources/docs -o public_html/docs/api
```

## Licence

Project Licenced to OxygenProject. [Copyright Elegant Media](https://www.elegantmedia.com.au)