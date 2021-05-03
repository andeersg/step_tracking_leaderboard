# Step Tracker Competition

Fetches step data from Google Fit and stores it in a SQL database.

## Commands

Run the command to fetch data.

```
php bin/console app:steps:sync
```

Create a new entity

```
php bin/console make:entity
```

Create database migration script 

```
php bin/console make:migration
```

Import changes into database

```
php bin/console doctrine:migrations:migrate
```