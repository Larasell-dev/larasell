---
title: Installation
description: Install Larasell in a Laravel application.
---

# Installation

Install Larasell with Composer:

```bash
composer require larasell/larasell
```

Larasell registers its service provider through Laravel package
discovery. After installing the package, run the migrations:

```bash
php artisan migrate
```

## Publishing files

The package loads its migrations automatically, so publishing them is
optional. Publish the migrations when you want to customize the table
structure before running them:

```bash
php artisan vendor:publish --tag=larasell-migrations
```

Publish the configuration file when you want to use custom model
classes:

```bash
php artisan vendor:publish --tag=larasell-config
```

The configuration file will be published to `config/larasell.php`.

