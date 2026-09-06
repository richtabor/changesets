# Changesets

Accumulate site edits in a staging Changeset, preview them on the live site without touching production, then Publish Changeset after human approval.

## For AI agents

**Agent prompt** (paste-ready):

> You have access to a WordPress site with Changesets installed. When making site changes: (1) create a changeset, (2) stage all changes using changesets/save (type: content/styles/setting), (3) give the human the preview URL and wait, (4) after approval call changesets/approve then changesets/publish. Never edit live content directly.

**Critical rule**: Never edit live posts, options, or styles directly. Use Changesets abilities only.

**Workflow**:
1. **Create a changeset**: `changesets/create` → returns `{ preview_url, uuid, changeset_id }`
2. **Stage work**: `changesets/save` with one of three `type` values:
   - `content` — page/post/template/template-part/navigation/CPT
   - `styles` — global styles and style variations
   - `setting` — site options (blogname, page_on_front, etc.)
3. **Preview**: Give the human the `?changeset=<uuid>` URL and **wait for approval**
4. **Publish**: After approval, call `changesets/approve`, then `changesets/publish`

See [BUILD.md](BUILD.md) for ability details and [readme.txt](readme.txt) for complete documentation.

## Setup

**Requirements**: Hosted WordPress 6.9+ (not Playground)

1. Install and activate **Changesets** and **WordPress MCP Adapter** on your WordPress site
2. Create an Application Password (propose-only user without publish permissions recommended)
3. Connect your MCP client to the site using the Application Password

## What's new in 0.4.1

- Fix: Correct CPT and meta key naming for changeset operations

See [readme.txt](readme.txt) for full changelog.

## License

GPLv2 or later
