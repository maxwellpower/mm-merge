# Mattermost User Merge

GUI/CLI tool to facilitate forcefully merging two Mattermost users into one.

**This tool ONLY supports Mattermost installs using a Postgres Database.**
Mattermost installs using a MySQL database are not supported by this tool!

## Important Warnings

1. **Destructive Operations**: This tool performs destructive operations on the database. The user being merged is
   completely deleted from the system after the merge. There is no ability to recover your data after the merge is
   complete, besides a database restore!
2. **Backup**: Always back up your Mattermost database before using this tool. This ensures you can recover data in case
   of any issues!
3. **Testing**: Always test this tool in a non-production or staging environment before using it live! This ensures you
   understand how the tool works and can recover from any issues, should they arise.
4. **Database Version**: Ensure the tool is still compatible with the version of the Mattermost database you're using.
   Database schemas can change over time. This tool was last tested on Mattermost `v8.1`.
5. **No Direct Support**: This script is not directly supported by the Mattermost Support Team. It's designed for use
   by the Mattermost Customer Success Team. Please do not contact the Mattermost Support Team for support of this
   script. This script should only be run after consultation with a member of the Mattermost Customer Success Team.

## Usage

Run the container with Docker or similar, then connect to `http://localhost:8080` or `https://localhost:8443`.

This script uses a direct database connection to make user changes. Add your database credentials to the `.env` file or
enter them as environment variables.

### Docker

#### Backrounded Container

##### Environment Variables

```bash
docker run -d -p 8080:80 -p 8443:443 --rm --name mm-merge \
  -e DB_USER=<Database User> \
  -e DB_PASSWORD=<Database Password> \
  -e DB_HOST=<Database Hostname> \
  -e DB_NAME=mattermost \
  #-e DB_PORT=<your_DB_PORT> \ # Optional, defaults to 5432
  ghcr.io/maxwellpower/mm-merge
```

##### `.env` File

```bash
cp default.env .env
vi .env
docker run -d --rm --name mm-merge -p 8080:80 -p 8443:443 --env-file=.env ghcr.io/maxwellpower/mm-merge
```

#### Interactive Container

##### Environment Variables

```bash
docker run -it -p 8080:80 -p 8443:443 --rm --name mm-merge \
  -e DB_USER=<Database User> \
  -e DB_PASSWORD=<Database Password> \
  -e DB_HOST=<Database Hostname> \
  -e DB_NAME=mattermost \
  #-e DB_PORT=<your_DB_PORT> \ # Optional, defaults to 5432
  ghcr.io/maxwellpower/mm-merge
```

##### `.env` File

```bash
cp default.env .env
vi .env
docker run -it --rm --name mm-merge -p 8080:80 -p 8443:443 --env-file=.env ghcr.io/maxwellpower/mm-merge
```

### Command Line Only

If you do not have access to a web browser, you can run the script from the command line. This is useful if you are
running the script on a remote server.

```bash
curl -X POST localhost:8080 \
     -d "old_user_id=[OLD_USER_ID]" \
     -d "new_user_id=[NEW_USER_ID]" \
     -d "force_authdata_checkbox=true" \
     -d "force_username_checkbox=false" \
     -d "force_username=[SPECIFIED_USERNAME]" \
     -d "force_email_checkbox=false" \
     -d "force_email=[SPECIFIED_EMAIL]" \
     -d "dry_run_checkbox=true" \
     -d "debug_checkbox=true"
```

**Replace**:

- `localhost:8080` with the URL where the form submits its data.
- `[OLD_USER_ID]` with the ID of the user account to purge.
- `[NEW_USER_ID]` with the ID of the user account to remain.

**Note**:

The `force_username_checkbox` and `force_email_checkbox` are optional. If you want to force a username,
set `force_username_checkbox` to `true` and `force_username` to the desired username. If you want to force an email
address, set `force_email_checkbox` to `true` and `force_email` to the desired email address.

The `dry_run_checkbox` and `debug_checkbox` are optional. If you want to run the script without making any
changes, set `dry_run_checkbox` to `true`. If you want to see the raw SQL queries being run, set `debug_checkbox` to
`true`.

## Output

Detailed output is available in the browser while running the tool and in the container console. To view logs in the
container run `docker logs -f mm-merge`.

To view the logs without backgrounding the container, start the container without the `-d` flag and replace it
with `-it`. Then, you can view the logs in the console and exit with `Ctrl+C`.
