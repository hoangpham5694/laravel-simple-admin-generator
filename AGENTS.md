# Testing

Run package tests in the existing PHP container. Do not use the host PHP CLI.

The database tests require MySQL. The `docker82-mysql-1` container owns the
configuration; read its environment at runtime and pass it to PHPUnit as the
`SAG_TEST_DB_*` variables. Never print, commit, or hard-code credentials.

```sh
task_mysql_env="$(docker inspect docker82-mysql-1 --format '{{range .Config.Env}}{{println .}}{{end}}')"
task_db_name="$(printf '%s\n' "$task_mysql_env" | sed -n 's/^MYSQL_DATABASE=//p')"
task_db_user="$(printf '%s\n' "$task_mysql_env" | sed -n 's/^MYSQL_USER=//p')"
task_db_password="$(printf '%s\n' "$task_mysql_env" | sed -n 's/^MYSQL_PASSWORD=//p')"

docker exec \
  -e SAG_TEST_DB_DATABASE="$task_db_name" \
  -e SAG_TEST_DB_HOST=mysql \
  -e SAG_TEST_DB_PORT=3306 \
  -e SAG_TEST_DB_USERNAME="$task_db_user" \
  -e SAG_TEST_DB_PASSWORD="$task_db_password" \
  docker82-workspace-1 \
  sh -lc 'cd /var/www/html/laravel_package_development/simple-admin-generation && composer test'
```

This suite runs migrations and writes test records to the configured MySQL
database. Run it only against the Docker development/test database.
