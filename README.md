# AutoFeedback webapp

Laravel-based webapp for early feedback on code submissions.
Uses separate resource-constrained Docker containers to run builds.
Persistent data is preserved across Docker containers by using volumes.

## Development environment

First, make sure you have installed Docker and Docker Compose.
In Linux, you should be able to [run "docker" without "sudo"](#docker-without-sudo).
In Windows, you will need to install [Docker Desktop](https://docs.docker.com/docker-for-windows/install/) with the [WSL2 backend](https://docs.docker.com/docker-for-windows/wsl/), and a recent [Ubuntu distribution for WSL2](https://ubuntu.com/wsl).

To start the development environment, run this command:

```sh
./dev-compose.sh up
```

You can navigate the web application via [http://localhost:3000](http://localhost:3000).

You should use `./dev-compose.sh` to run any Docker Compose command on the development environment.
If you want to run Artisan, you can use `./dev-artisan.sh` as a shorthand.

The development environment uses bind mounts and automatically fixes UID/GIDs inside the containers, so you can work on the code from your IDE.
It will also run Laravel Mix in the background for you, using its own Node Docker container.

The Docker container will install dependencies, generate the key, and migrate the database on its first run.
You will still need to seed the database as usual, and you will want the development-time users as well:

```sh
./dev-artisan.sh db:seed
./dev-artisan.sh db:seed --class=DevelopmentUserSeeder
```

### Docker without sudo

If you are using Linux and want to run `docker` without `sudo` (needed for the scripts and for testing from PhpStorm), your regular user should be able to access the Docker socket at /var/run/docker.sock (no need for "sudo docker").
To do this, run this command to add yourself to the `docker` group:

```sh
sudo usermod -a -G docker $(whoami)
```

Keep in mind that this effectively gives root access to the user in question: this should be a machine that you own yourself.
If this is a problem, in theory you could use [Rootless Docker](https://docs.docker.com/engine/security/rootless/), but this may not work well with bind mounts, and will not be able to enforce resource constraints via Linux cgroups.

### Git hooks

It is recommended to set up the Git hooks in the `hooks` directory. You can do so from a UNIX terminal (or Git Bash in Windows):

```sh
bin/create-hook-symlinks
```

You will need to install `mdl` (e.g. via `sudo gem install mdl`).

## Running tests

### Running tests from the console

To run tests on the development environment, run this command:

```sh
./dev-test.sh
```

To run tests on a production-like environment (without bind mounts), as done in the Gitlab CI pipeline, run these commands:

```sh
./it-build.sh
./it-test.sh
```

### Running tests from PhpStorm

You will need to go to "File - Settings - Languages and Frameworks - PHP", and change the settings as follows:

* PHP language level: 7.4.
* CLI interpreter: click on "..." to create a new remote interpreter based on Docker Compose (see below).
* Path mappings: map the `webapp` local directory to the `/app` remote directory.

For the Docker Compose interpreter, use these steps:

* Click on "+" and select "From Docker...".
* Pick "Docker Compose".
* Click on "Server - New...".
* If on Linux, select "Unix socket"; if on Windows, select "Docker for Windows". Click on OK.
* For the "PHP interpreter path", enter `php`. Leave everything else as is and click on OK.
* For the configuration files, click on the folder icon, and then make sure these files are listed in this order:
    * docker-compose.yml
    * docker-compose.dev.yml
    * docker-compose.ldap.yml
* For "service", select "test-runner".
* For "Lifecycle", select "Always start a new container". You will need to have the development environment up before running tests: this is needed because PhpStorm does not use our entrypoint scripts when running tests, so the container won't check that MariaDB and Redis are up.
* Ensure that everything works by clicking on the looping arrows to the right of "PHP executable". After some time, it should tell you the version of PHP in the Docker container, the configuration file being used, and the version of the debugger.
* Finally, click on OK.

To run the PHPUnit tests, create a new configuration which runs PHPUnit using the settings in the `phpunit.xml` file.
In the Run/Debug configuration, make sure that these settings are used:

* Test scope: Defined in the configuration file.
* Use alternative configuration file: checked and pointing at `webapp/phpunit.xml`.
* Preferred Coverage engine: XDebug.
* Interpreter: the one based on Docker Compose that we defined above.

### Debugging page loads from PHPStorm

If you want to be able to load any webpage and place breakpoints on the PHP code that serves it, follow these steps.

First, go to "File - Settings - Servers" and add a server:

1. The name does not matter: call it "Docker Container", for instance.
1. Set the "Host" and "Port" to `localhost` and `3000`, and leave "XDebug" as the debugger.
1. Tick the "Use path mappings", and make sure to map your project folder to `/app`.
1. Click on OK.

Now go to "Run - Edit Configurations...", and add a new "PHP Remote Debug" configuration.
You do not need to customise anything - simply press OK.

You should be able to now run this configuration, and PHPStorm will tell you that it has started listening for incoming PHP debug connections.
Simply reload the page, and if you hit any breakpoints you should see PHPStorm highlight the relevant line and allow you to do step-by-step debugging.

Once you are done, select "Run - Stop Listening for PHP Debug Connections".
Webpages should not trigger your breakpoints anymore.

## Docker specifics

### Compose files

This project uses multiple Docker Compose files to avoid repetition between the various execution environments:

* `docker-compose.yml` is the base layer, which uses the `*:production` images, and defines all the common services and options.
* `docker-compose.dev.yml` builds on top of `docker-compose.yml`, switching to the `*:development` Docker images, and using bind mounts for live development and adding a service which runs Laravel Mix in the background. It also sets various usernames and passwords.
* `docker-compose.vols.yml` builds on top of `docker-compose.yml`, and uses volumes to share data across the nginx web server (for public files), the main `php-fpm` worker pool (for request processing), the Java queue workers, and the default queue workers.
* `docker-compose.itenvs.yml` builds on top of `docker-compose.vols.yml`, setting usernames and passwords.
* `docker-compose.itimgs.yml` builds on top of `docker-compose.itenvs.yml`, setting images based on environment variables. This is used in Gitlab CI.
* `docker-compose.ldap.yml` provides a development-time OpenLDAP server with some seeded users (see `docker/ldap/custom.ldif`).

The various environments would work as follows:

* Development: `docker-compose.yml` + `docker-compose.dev.yml` + `docker-compose.ldap.yml`.
* Integration testing: `docker-compose.yml` + `docker-compose.vols.yml` + `docker-compose.itenvs.yml` + `docker-compose.itimgs.yml` + `docker-compose.ldap.yml`.
* Production: `docker-compose.yml` (updated by CI) + `docker-compose.vols.yml` (updated by CI) + `docker-compose.local.yml` (hand written and protected, not kept by CI: you can base it off `docker-compose.itenvs.yml`). LDAP is supposed to run on its own, separately from this application.

In a production environment with continuous deployment, the images should be pulled from the Gitlab private repository after testing.

### Updating the development Docker containers

If you make any change to the Docker containers, you can rebuild them with:

```sh
./dev-compose.sh build
```

### Updating the integration test Docker containers

For changes to the integration test Docker containers (which do not use bind mounts), you can rebuild them with:

```sh
./it-build.sh
```

### Managing the local database

There are a number of support scripts to help manage the development database. To backup the database:

```sh
./dev-db-dump.sh dump.sql
```

To restore the database from that dump:

```sh
./dev-db-restore.sh dump.sql
```

To open a MariaDB SQL shell:

```sh
./dev-db-sql.sh
```