# Mattermost User Merge Tool

A simple web application designed to merge two Mattermost users into one.

**Currently, this tool only supports Mattermost installs using a Postgres Databases. MySQL support is planned for a
future release.**

## Important Notice

This script is provided as-is with no warranty or guarantee. Please test this script in a non-production environment
before using it in production. This script is not directly supported by Mattermost and was created for use by the
Mattermost Customer Success team. Please do not contact the Mattermost support team for support of this script.

This script is extremely destructive and can cause data loss if used incorrectly. Please ensure you have a backup of
your database before using this script. The user being merged is completely deleted from the system after the merge.

## Usage

Run the container with Docker or similar then connect to `http://localhost:8080`. You can update the local port used in
the command below by modifying `-p 8080:80` to `-p <your_port>:80`.

This script uses a direct database connection to make the user changes. Add your database credentials to the `.env` file
or pass them in as environment variables.

### Docker

- with environment variables
    ```bash
    docker run -d -p 8080:80 --rm --name mm-merge \
      -e PG_USER=<your_pg_user> \
      -e PG_PASSWORD=<your_pg_password> \
      -e PG_HOST=<your_pg_host> \
      -e PG_DATABASE=<your_pg_database> \
      -e PG_PORT=<your_pg_port> \
      ghcr.io/maxwellpower/mm-merge
  ```

- with the included .env file

    ```bash
    cp default.env .env
    vi .env
    docker run -d --rm --name mm-merge -p 8080:80 --env-file=.env ghcr.io/maxwellpower/mm-merge
    ```
