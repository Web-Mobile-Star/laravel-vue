# Deployment instructions for Ubuntu-based servers

[[_TOC_]]

## Basic dependencies

First, install [Docker](https://docs.docker.com/engine/install/ubuntu/). To save disk space, you may want to edit /etc/docker/daemon.json to use the `local` log driver. This way, it will keep 100MB of logs (default is 5 file rotation, with each growing up to 20MB):

```json
{"log-driver": "local"}
```

Then, you will want to install [Docker Compose](https://docs.docker.com/compose/install/).

## Gitlab deploy token

Create a deploy token for the server (Settings - Repository - Deploy Tokens). It should be named after the hostname of the server, and it should have the `read_repository` and `read_registry` scopes. Note down the username and password - you will only be able to see the password once.

SSH into the server and do a shallow clone of the current `master` tip in your `$HOME`. Run this command, putting a space before "git" so it will not be saved in `.bash_history`:

```sh
cd $HOME
 git clone --depth=1 \
   https://gitlab.com/docker-autofeedback/autofeedback-webapp.git
```

Log the server into the private Docker repository for the project. Again, add a space before the first word of the command so it will not be saved into your Bash history:

```sh
 sudo -H docker login -u username -p password registry.gitlab.com
```

The `-H` flag is needed so the auth token will be saved in /root and not in your user's home directory.

## Docker Compose configuration

Create `/srv/autofeedback` and copy over the relevant files:

```sh
cd $HOME/autofeedback-webapp
sudo mkdir /srv/autofeedback
sudo cp docker-compose.yml /srv/autofeedback
sudo cp docker-compose.vols.yml /srv/autofeedback
sudo cp docker-compose.itenvs.yml /srv/autofeedback/docker-compose.local.yml
sudo cp deployment/prod-compose.sh /srv/autofeedback/compose.sh
sudo chmod 750 /srv/autofeedback/compose.sh
sudo chmod 640 /srv/autofeedback/docker-compose.local.yml
```

Tweak the `docker-compose.local.yml` file as in the subsections below.

### General Docker Compose tweaks

* Add `image` keys to the `nginx`, `app`, `java-worker` and `default-worker` services pointing to the appropriate tag in the private Docker repository.

### Core webapp (app, workers, Redis, MariaDB)

1. Set `APP_DEBUG` to `false`.
1. Set `APP_ENV` to `production`.
1. Set `APP_KEY` to an application key generated from your dev environment with `./dev-artisan.sh key:generate --show`.
1. Set `APP_TIMEZONE` to the appropriate timezone to be used in the server: choose from [the supported timezones in PHP](https://www.php.net/manual/en/timezones.php).
1. Set `APP_URL` to the public-facing URL for the server.
1. Set `MARIADB_USER`/`DB_USERNAME`, `MARIADB_DATABASE`/`DB_DATABASE`, and `MARIADB_PASSWORD`/`DB_PASSWORD` appropriately.
1. Set `MARIADB_ROOT_PASSWORD` with a generated password.
1. Set all occurrences of `REDIS_PASSWORD` with a generated password.

### Main web server (nginx)

1. Set the `ports` key in the `nginx` service so the 8080 port in the nginx service is exposed through the relevant port in your server.
1. Make sure `SERVER_NAME`, `SERVER_PORT`, `HTTP_HOST` and `REQUEST_SCHEME` are set respectively to the public-facing hostname, port, hostname+port (e.g. `host1` or `host2:3000`), and scheme (`http` or `https`). These should agree with the value of `APP_URL` in the core webapp.

Check if your server has IPv6 disabled, by running this command:

```shell
cat /sys/module/ipv6/parameters/disable
```

If it produces "1", then it is disabled, and you will need to tweak the Compose configuration so it will only bind to IPv4:

```yaml
  nginx:
    image: registry.gitlab.com/autofeedback/autofeedback-webapp/nginx:production
    environment: ...
    ports:
      - 0.0.0.0:3000:8080
```

### WebSockets server (laravel-echo-server)

Generate a new `APP_ID` and a new `APP_KEY` with the suggested commands.
These are separate to the core webapp's `APP_NAME` and `APP_KEY`.

## OpenLDAP server

This app assumes you have an existing LDAP server already deployed and ready to use:

1. Set `LDAP_*` variables accordingly.
1. Test the connection to the LDAP server with `./compose.sh run --rm app php artisan ldap:test`.

If you need your own LDAP server for this application, you can start from the existing `docker-compose.ldap.yml` file in the repository.
This is only useful as a starting point: its default configuration is intended for development and testing, not for production.
Please [visit the official repository](https://github.com/osixia/docker-openldap/) for instructions on how to strengthen it.
In this case, you may want to add `WAIT_FOR_LDAP=true` to the `environment` of the `app` service to ensure it waits for the LDAP server to come up.

Either way, you will want to test the LDAP connection with this command:

```sh
sudo -H ./compose.sh exec app php artisan ldap:test
```

You can also disable the use of LDAP: to do so, add `APP_WEB_GUARD_PROVIDER=users` to the `environment` of the `app` service.

## Manual testing

Try bringing up the app manually, while watching the logs:

```sh
sudo -H ./compose.sh up
```

If you need to tweak any environment variables, edit the `.yml` files and then ask Docker Compose to recreate the containers:

```sh
sudo -H ./compose.sh up -d
```

## Reverse proxying

If you need to put `nginx` behind an Apache reverse proxy, you can use the `deployment/apache/*.conf` files as a starting point.

You will need to enable the `proxy_wstunnel` module:

```shell script
sudo a2enmod proxy_wstunnel
```

The files use the settings and SSL certificate generated by [Certbot](https://certbot.eff.org/).
You will want to generate the appropriate certificate with:

```shell script
sudo certbot -d your.domain
```

You will also need to make sure that the appropriate proxy IP is being used in the `TrustProxies` middleware.
By default, the host to the Docker containers (which is the gateway to the Docker Compose network) is trusted.
If you need to change this, you can set the `TRUSTED_PROXY` environment variable in your `docker-compose.local.yml` file.

You should test the SSL certificate in [SSL Labs](https://www.ssllabs.com/ssltest/) once it is working.
Check that there is a cron job / systemd timer to renew the SSL certificates as well.

## Admin user

Use the LDAP user import feature to create your application user:

```sh
./compose.sh exec app php artisan ldap:import ldap your.email@domain.com
```

Then use Tinker to give yourself the superuser role:

```sh
./compose.sh exec app php artisan tinker
>>> $u = User::where('email', 'your.email@domain.com')->first();
>>> $u->assignRole(User::SUPER_ADMIN_ROLE);
```

You can now log into the application and continue your setup.
Keep in mind that only users that have been imported in advance may log into the app.

## Set up as a system service

Once you have checked that it works, copy over the `deployment/autofeedback.service` to the systemd services, enable it:

```sh
sudo cp $HOME/autofeedback-webapp/deployment/autofeedback.service /etc/systemd/system/autofeedback.service
sudo systemctl daemon-reload
sudo systemctl enable autofeedback
sudo systemctl start autofeedback
```

You will also want to set up a daily job to prune Docker artifacts:

```shell script
sudo cp $HOME/autofeedback-webapp/deployment/cron.daily/docker-prune /etc/cron.daily
```

## Gitlab runner

If you have a runner with the `production` tag, you will be able to automatically update the running service on every push.
To do so, [install](https://docs.gitlab.com/runner/install/) the gitlab-runner tool, [register](https://docs.gitlab.com/runner/register/index.html) a runner with the `production` tag, and then add these lines to your `sudoers` file with `sudo visudo`:

```text
gitlab-runner ALL=(ALL) NOPASSWD: /srv/autofeedback/compose.sh pull
gitlab-runner ALL=(ALL) NOPASSWD: /srv/autofeedback/compose.sh stop nginx
gitlab-runner ALL=(ALL) NOPASSWD: /srv/autofeedback/compose.sh up -d
gitlab-runner ALL=(ALL) NOPASSWD: /usr/bin/docker login *
```

You will need to set up two CI variables in Gitlab for the `production` environment:

* `COMPOSE_SCRIPT`: specific command needed to run the `compose.sh` script (including `sudo` if needed).
* `WEBSITE_URL`: full URL to your deployment.

## Periodic backups

The `deployment/cron.daily` folder has a number of `backup-*` scripts that should be copied into the `/etc/cron.daily` system folder to run daily backups of the MariaDB database and Laravel storage volumes.

Additionally, the `deployment/logrotate` folder includes a configuration to be added to `/etc/logrotate.d` for rotating between daily backups over the last 2 weeks.

## Monitoring

[UptimeRobot](https://uptimerobot.com/) monitors the production website.

## Artisan commands

### autofeedback:checksum-assessment

Takes the ID of an assessment, and schedules checksum calculation jobs for those submissions that do not have a checksum yet.
Mostly useful when upgrading a server: should not be necessary for any new submissions.
Use as follows (where `ID` is the identifier of the assessment):

```sh
./compose.sh run --rm app php artisan autofeedback:checksum-assessment ID
```