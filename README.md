# Media mpx for Drupal 8

[![CircleCI](https://circleci.com/gh/Lullabot/media_mpx.svg?style=svg)](https://circleci.com/gh/Lullabot/media_mpx) [![Maintainability](https://api.codeclimate.com/v1/badges/69160a3010c6788be915/maintainability)](https://codeclimate.com/github/Lullabot/media_mpx/maintainability) [![Test Coverage](https://api.codeclimate.com/v1/badges/69160a3010c6788be915/test_coverage)](https://codeclimate.com/github/Lullabot/media_mpx/test_coverage)

This module integrates [mpx for PHP](https://github.com/Lullabot/mpx-php) with
Drupal 8's Media API.

## Requirements

* Composer to fetch the various libraries this module is built on.
* Drupal 8.5+
* PHP 7.0+

## About the mpx name

mpx is not an acronym, and is used by thePlatform with capitals in all-caps
sentences. This makes for some odd displays in Drupal, that generally expect
title case or sentence case. When referring to mpx within the user interface
and in strings, use lower case such as 'the mpx User entity'.

## Migrating from Media: thePlatform mpx

Migrations should be run with Drush, using the
[migrate-tools](https://www.drupal.org/project/migrate_tools) module.

Currently, Users and Accounts are migrated from mpx Accounts in Drupal 7. As
migrating passwords would require access to the Drupal 7 private key, they
are not migrated. Most Drupal 8 sites should inject passwords through
`settings.php` or environment variables. For example, if the migrated user
machine name is `mpx_lullabot`, add the following to `settings.php`:

```php
<?php

$config['media_mpx.media_mpx_user.lullabot_mpx']['password'] = 'SECRET';
```
