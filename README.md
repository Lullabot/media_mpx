# Media mpx for Drupal 8

[![Maintainability](https://api.codeclimate.com/v1/badges/69160a3010c6788be915/maintainability)](https://codeclimate.com/github/Lullabot/media_mpx/maintainability) [![Test Coverage](https://api.codeclimate.com/v1/badges/69160a3010c6788be915/test_coverage)](https://codeclimate.com/github/Lullabot/media_mpx/test_coverage)

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

## Thumbnail integration

When an mpx media item is saved, Drupal will download the default thumbnail so
it can be used with image styles.

Consider enabling "Queue thumbnail downloads" in your media type configuration,
depending on your editorial workflow and the number of videos created each day.
When this setting is off, expect an additional performance hit of a few hundred
milliseconds when importing videos. One advantage of queueing thumbnails is
that [Concurrent Queue](https://www.drupal.org/project/concurrent_queue) can be
used to download thumbnails in parallel. Until thumbnails are downloaded, a
placeholder will be used in admin listing (and on your site, if videos are
published automatically).

## Limiting results during an mpx import

Sometimes sites will want to add code to alter how data is imported into
Drupal. For example, an mpx account could have a custom field to indicate if
a given asset should be ignored by Drupal. Since ignoring assets is a common
use case, we can't use the normal entity hooks.

Sites wishing to limit the mpx data that is imported will need to implement two
event subscribers.

### 1. Altering import mpx queries

First, it is a best practice to limit the returned data from mpx where
possible. This is possible by subscribing to the
`\Drupal\media_mpx\Event\ImportSelectEvent::IMPORT_SELECT` event and by
altering the `ObjectListQuery` object attached to that event. See
[\Drupal\media_mpx_test\Event\ImportSelectSubscriber](test_modules/media_mpx_test/src/Event/ImportSelectSubscriber.php)
for an example. To add your own in a custom module, create a similar class and
add the following in your module's `mymodule.services.yml`.

```yml
services:
  mymodule.import_select_subscriber:
    class: Drupal\mymodule\Event\MyImportSubscriber
    tags:
      - { name: event_subscriber }
```

### 2. Altering ingested mpx objects

Unfortunately, mpx has no ability to filter results in notifications. While the
above subscriber will work for initial imports, it will not work for
notifications.

During an import, an `ImportEvent` is dispatched with references to each media
entity that would be created or updated by the import.

Note that this event should _not_ be used for mapping mpx data to Drupal
fields. Instead, use the mapping functionality in the media type configuration.

See
[\Drupal\media_mpx_test\Event\ImportEventSubscriber](test_modules/media_mpx_test/src/Event/ImportEventSubscriber.php)
for an example implementation.

## Custom field support

Custom fields are defined in mpx and allow for additional data to be attached
to media and other mpx objects. Custom fields are grouped by _namespaces_ and
each object can have custom fields from any number of namespaces.

Out of the box, custom fields will not be available on loaded mpx objects.
If custom fields are used in mpx, but are not required in Drupal, then you
can leave things as is and each set of custom fields will be represented by the
`\Lullabot\Mpx\Normalizer\MissingCustomFieldsClass` class.

To integrate custom fields:

1. Follow the steps at https://github.com/Lullabot/mpx-php to generate the
   initial custom fields classes. For example, if you wish to include the
   classes in the `mysite_mpx` module, run:
   `bin/console mpx:create-custom-field '\Drupal\mysite_mpx\Plugin\media_mpx\CustomField' 'Media Data Service' 'Media' '1.10'`
1. Rename the classes to something that is reasonable for each set of data they
   contain.
1. Move the classes to `src/Plugin/media_mpx/CustomField` in your custom
   module.
1. Clear caches. When loading a Media item, your classes will be returned when
   calling `getCustomFields($namespace)`, where `$namespace` is the mpx
   namespace of your fields.

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

$config['media_mpx.media_mpx_user.mpx_lullabot']['password'] = 'SECRET';
```
