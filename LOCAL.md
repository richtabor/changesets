# Local Development

Run WordPress with Changesets and MCP Adapter locally using wp-env.

## Prerequisites

- **Docker Desktop** (running)
- **Node.js** 18+ and npm
- **wp-env**: `npm install -g @wordpress/env`

## Quick Start

```bash
# Start WordPress
npx wp-env start

# Or if wp-env is installed globally
wp-env start
```

The site will be available at:
- **Development site**: http://localhost:8888
- **Tests site**: http://localhost:8889
- **WP Admin**: http://localhost:8888/wp-admin
  - Username: `admin`
  - Password: `password`

## What's Installed

wp-env automatically installs:
1. **Changesets plugin** (this repo, mapped from `.`)
2. **WordPress MCP Adapter** (from WordPress/mcp-adapter trunk)
3. **Twenty Twenty-Five theme** (block theme)

**Note**: If plugins aren't automatically activated, use the troubleshooting command below.

## Creating an Application Password

Application Passwords work on local HTTP without SSL configuration.

1. Go to http://localhost:8888/wp-admin/profile.php
2. Log in as `admin` / `password`
3. Scroll down to **Application Passwords**
4. Enter a name (e.g., "MCP Client")
5. Click **Add New Application Password**
6. **Copy the generated password immediately** (shown once)

## MCP Client Configuration

Configure `@automattic/mcp-wordpress-remote` to connect to your local MCP Adapter:

```json
{
  "mcpServers": {
    "wordpress-local": {
      "command": "npx",
      "args": [
        "-y",
        "@automattic/mcp-wordpress-remote"
      ],
      "env": {
        "WP_API_URL": "http://localhost:8888/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "admin",
        "WP_API_PASSWORD": "<your-application-password>",
        "OAUTH_ENABLED": "false"
      }
    }
  }
}
```

Replace `<your-application-password>` with the Application Password you created above.

## Smoke Test Checklist

Test the full Changesets workflow via MCP:

1. **Create changeset**: Call `changesets/create` → get `{ preview_url, uuid, changeset_id }`
2. **Save changes**: Call `changesets/save` with `type: content` (e.g., update a post)
3. **Preview**: Open the `?changeset=<uuid>` URL → verify staged changes appear
4. **Approve**: Call `changesets/approve`
5. **Publish**: Call `changesets/publish` → verify changes go live

## Useful Commands

```bash
# Stop the environment
wp-env stop

# Reset (clean database, reinstall)
wp-env clean all
wp-env start

# View logs
wp-env logs

# Run WP-CLI commands
wp-env run cli wp plugin list
wp-env run cli wp theme list
wp-env run cli wp option get siteurl

# Access MySQL
wp-env run cli wp db cli
```

## Troubleshooting

**Plugins not active after start?**
```bash
wp-env run cli wp plugin activate changesets mcp-adapter
```

**Need to reinstall MCP Adapter?**
```bash
wp-env clean all
wp-env start
```

**Port 8888 already in use?**
Edit `.wp-env.json` and change `"port": 8888` to another port.

**Docker not running?**
Start Docker Desktop before running `wp-env start`.

## Not for MCP Agent Testing

⚠️ **Playground** is no longer the recommended approach for MCP agent testing. Use this wp-env setup instead for the full MCP agent loop with real HTTP endpoints.
