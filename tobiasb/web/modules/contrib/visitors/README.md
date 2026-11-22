# Visitors

Visitors is a powerful, native Drupal web analytics tool empowering site builders and administrators with comprehensive insights into user behavior and site performance.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/visitors).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/visitors).


[![pipeline status](https://git.drupalcode.org/project/visitors/badges/8.x-2.x/pipeline.svg)](https://git.drupalcode.org/project/visitors/-/commits/8.x-2.x)


[![coverage report](https://git.drupalcode.org/project/visitors/badges/8.x-2.x/coverage.svg)](https://git.drupalcode.org/project/visitors/-/commits/8.x-2.x)


## Requirements

This module requires the following modules:

- [Charts](https://www.drupal.org/project/charts)



## Installation

Install as you would normally install a contributed Drupal module. For further information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

### Composer

Composer is the preferred method to install visitors.

<code>composer require 'drupal/visitors:^2'</code>

## Configuration

1. Enable the module at **admin/modules**
1. Configure **admin/config/system/visitors**

## Statistics node counts

Core Statistics stores aggregate node counts in `node_counter` (`nid`,
`totalcount`, `daycount`, `timestamp`). It does not store each hit, so this
migration fills `visitors_counter` and does not create visit rows.

Enable Migrate, and Migrate Tools if you import with Drush. Point a database
connection named `migrate` at the Drupal 8 database. Visitors reads that
connection by default. See [Migrating data from a SQL source](https://www.drupal.org/docs/drupal-apis/migrate-api/migrating-data-from-a-sql-source).

When the rebuilt nodes keep their IDs, import after the node migrations:

```bash
drush migrate:import visitors_statistics_node_counter
```

When node IDs change, name the node migrations in
`hook_migration_plugins_alter()`:

```php
function my_module_migration_plugins_alter(array &$migrations) {
  if (!isset($migrations['visitors_statistics_node_counter'])) {
    return;
  }
  $migrations['visitors_statistics_node_counter']['source']['node_migrations'] = [
    'my_node_migration',
  ];
}
```

A node that was not migrated is skipped. A second import with
`drush migrate:import visitors_statistics_node_counter --update` replaces the
counts for migrated nodes. Import before the new site records its own views.
`drush migrate:rollback visitors_statistics_node_counter` deletes only the
rows this migration wrote.

`daycount` is copied to `today`. Visitors cron sets every `today` value back
to 0 after a day.

If Statistics is installed on the same site, the form at Administration,
Configuration, System, Visitors, Statistics copies `node_counter` and
uninstalls Statistics. Use the migration above when the old table is on
another database.

## Development

If you haven't already, [install Docker and DDEV](https://ddev.readthedocs.io/en/latest/users/install/)

<pre>
git clone git@git.drupal.org:project/visitors.git
cd visitors
ddev config --project-type=drupal --docroot=web --php-version=8.3 --corepack-enable --project-name=visitors
ddev add-on get ddev/ddev-drupal-contrib
ddev add-on get ddev/ddev-selenium-standalone-chrome
ddev start
ddev poser
ddev symlink-project
</pre>


## Maintainers

- Steven Ayers - [bluegeek9](https://www.drupal.org/u/bluegeek9)
