# Changesets

Accumulate site edits in a staging Changeset, preview them on the live site without touching production, then Publish Changeset after human approval.

## For AI agents

**Agent prompt** (paste-ready):

> You have access to a WordPress site. Before staging changes: (1) Check if Changesets and WordPress MCP Adapter are installed and active by verifying `changesets/*` abilities are available via MCP. (2) If missing, explain both plugins are required, ask permission to install, then install if allowed. (3) Once plugins are active, ensure MCP connection with Application Password. Then workflow: create changeset → stage changes with changesets/save (type: content/styles/setting) → give human the preview URL and wait for approval → after approval call changesets/approve then changesets/publish. Never edit live content directly unless human explicitly requests a repair outside Changesets.

**Setup checklist** (verify BEFORE asking for credentials):
1. Are Changesets + MCP Adapter installed and active?
2. Verify by checking if `changesets/*` abilities are available via MCP tools
3. If missing: Ask permission to install, then install if allowed
4. Only after plugins are active: Request Application Password / MCP connection if not connected

**Why**: Site URL alone ≠ MCP access. You need the adapter plugin + authenticated connection for `changesets/*` tools to appear.

**Critical rule**: Never edit live posts, options, or styles directly. Use Changesets abilities only. Only bypass Changesets if human explicitly asks for a live repair.

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

## What's new in 0.4.2

- Fix: Publish correctly promotes new pages to live status by clearing staged markers first
- Fix: Settings referencing staged content (like `page_on_front`) are remapped to final live IDs
- Fix: Publish returns accurate results including failed items
- New: `changesets/discard` ability to trash changesets and delete staged drafts
- New: `changesets/status` ability for setup verification and readiness checks
- Enhanced: `changesets/get` includes staged options, styles, and variations
- Improved: Agent setup docs require plugin verification before credentials

See [readme.txt](readme.txt) for full changelog.

## License

GPLv2 or later
