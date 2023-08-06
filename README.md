# Mattermost User Merge Tool

GUI tool to facilitate forcefully merging two Mattermost users into one.

**This tool only supports Mattermost installs using a Postgres Database.**

## Important Warnings

1. **Destructive Operations**: This tool performs destructive operations on the database. The user being merged is
   completely deleted from the system after the merge.
2. **Backup**: Always back up your Mattermost database before using this tool. This ensures you can recover data in case
   of any issues.
3. **Testing**: Test this tool in a non-production or staging environment before using it live.
4. **Database Version**: Ensure that the tool is compatible with the version of the Mattermost database you're using.
   Database schemas can change over time.
5. **No Direct Support**: This script is not directly supported by Mattermost. It was created for use by the Mattermost
   Customer Success team. Please do not contact the Mattermost support team for support of this script.
6. **Security**: Ensure that database connections are secure and sensitive data is handled appropriately.

## Usage

Run the container with Docker or similar, then connect to `http://localhost:8080` or `https://localhost:8443`.

This script uses a direct database connection to make user changes. Add your database credentials to the `.env` file or
enter them as environment variables.

### Docker

- With environment variables:

```bash
docker run -d -p 8080:80 -p 8443:443 --rm --name mm-merge \
  -e DB_USER=<Database User> \
  -e DB_PASSWORD=<Database Password> \
  -e DB_HOST=<Database Hostname> \
  -e DB_NAME=mattermost \
  #-e DB_PORT=<your_DB_PORT> \ # Optional, defaults to 5432
  ghcr.io/maxwellpower/mm-merge
```

- With the included .env file:

```bash
cp default.env .env
vi .env
docker run -d --rm --name mm-merge -p 8080:80 -p 8443:443 --env-file=.env ghcr.io/maxwellpower/mm-merge
```

## Output

Detailed output is available in the browser while running the tool and in the console. To view logs in the console,
run `docker logs mm-merge -f`.

To view the logs without backgrounding the container, start the container without the `-d` flag and replace it
with `-it`. Then, you can view the logs in the console and exit with `Ctrl+C`.
