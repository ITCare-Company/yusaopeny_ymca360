CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Configuration
* Maintainers

INTRODUCTION
------------

YUSA OpenY YMCA360 Integration module provides the Open Y distribution with an
option to pull YMCA360 program and live stream schedules into the Program Event
Framework.

* For a full description:
  https://drupal.org/project/yusaopeny_ymca360

* Issue queue for YUSA OpenY YMCA360 Integration:
  https://drupal.org/project/issues/yusaopeny_ymca360

REQUIREMENTS
------------

Open Y 8.2+.

INSTALLATION
------------

* Install normally as other modules are installed. For Support:
  https://www.drupal.org/docs/8/extending-drupal/installing-contributed-modules
* Enable two modules:
  * YMCA360 Integration
  * YMCA360 Integration - Schedules sync

HOW IT WORKS
------------

The module implements the cron job that pulls the schedules from YMCA 360 via
its REST API and stores the representing Activity, Class, and Session nodes in
Drupal database.

Underthehood the module uses the YMCA Sync module
(https://github.com/ymcatwincities/ymca_sync).

The created Activity nodes reference Program Subcategory nodes that you specify
upon module configuration.

The _YMCA360 Integration_ module enables common functionality for interacting with
YMCA 360 API.

The _YMCA360 Integration - Schedule sync_ module enables particular procedures
to sync schedules:
* provides the YMCA Sync 'syncer';
* makes it possible to run sync during regular Drupal cron runs.

If the sync executes by Drupal cron runs, it's recommended to run cron often to
keep the website schedules up-to-date (e.g., every 15 minutes).

See YMCA Sync documentation for examples on running sync using drush. 

CONFIGURATION
-------------

* Obtain credentials from YMCA360

* Configure the module in `Administration >> YMCA Website Services >>
  Integrations >> YMCA360 >> Settings`:
  * Specify credentials and submit the form.
  * If the credentials are correct select the schedules you want to be synced to
  your Open Y website:
    * tick checkboxes for the needed YMCA 360 schedules;
    * specify corresponding program subcategory nodes.
* Map YMCA 360 locations onto your website's locations in `Administration >>
YMCA Website Services >> Integrations >> YMCA360 >> Locations mapping`:
  * If you want to sync the live stream schedule, specify a branch node to be
associated with live stream classes.
  * Set the location/branch mapping for regular programs/classes:
    * specify the YMCA 360 location ID and local Open Y branch names separated by
a comma;
    * find the YMCA 360 location IDs below the mapping field.
* Enable the sync on the Schedule sync tab. 

RECOMMENDED MODULES
-------------------

It's advised to install **Ultimate Cron** (https://www.drupal.org/project/ultimate_cron)
so that it's possible to adjust how often your cron jobs run.

MAINTAINERS
-----------

Current maintainers:
* andreymaximov - https://www.drupal.org/u/andreymaximov
* Five Jars - https://fivejars.com/
