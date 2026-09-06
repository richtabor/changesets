# Changesets

Accumulate site edits in a staging Changeset, preview them on the live site without touching production, then Publish Changeset after human approval.

## For AI agents

**Agent prompt** (paste-ready):

> You have access to a WordPress site with Changesets installed. When making site changes: (1) call changesets/status to get the open changeset or create one, (2) stage all changes using changesets/save (type: content/styles/setting), (3) give the human the preview URL and wait, (4) after approval call changesets/approve then changesets/publish. Never edit live content directly.

**Critical rule**: Never edit live posts, options, or styles directly. Use Changesets abilities only.

**Workflow**:
1. **Check status**: `changesets/status` → returns open changeset or creates one
2. **Stage work**: `changesets/save` with one of three `type` values:
   - `content` — page/post/template/template-part/navigation/CPT (supports `featured_media`)
   - `styles` — global styles and style variations
   - `setting` — site options and theme mods (blogname, custom_logo, site_icon, etc.)
3. **Preview**: Give the human the `?changeset=<uuid>` URL and **wait for approval**
4. **Publish**: After approval, call `changesets/approve`, then `changesets/publish`

### Media policy

**Uploads persist immediately** — they go to the Media Library on upload and remain there even if the changeset is discarded. Changesets **never stage `attachment` posts**. Instead, stage only **references**:

- **Content**: HTML/blocks with attachment IDs already written by the editor
- **Featured images**: Pass `featured_media` (attachment ID) when staging content
- **Site logos/icons**: Pass `custom_logo` or `site_icon` (attachment ID) via `type=setting`

When the changeset is published, the references (IDs) are applied to live content. When discarded, uploaded media stays in the library but the references are dropped.

### What's stageable

**Content** (via `type=content`):
- Pages, posts, custom post types with `show_ui`
- Templates (`wp_template`), template parts (`wp_template_part`)
- Navigation menus (`wp_navigation`)
- Featured images (via `featured_media` field)

**Styles** (via `type=styles`):
- Style variations (by name)
- Global styles patches (partial theme.json settings/styles)

**Settings** (via `type=setting`):
- Options: `show_on_front`, `page_on_front`, `page_for_posts`, `blogname`, `blogdescription`, `site_icon`
- Theme mods: `custom_logo`
- Auto-detected storage (`custom_logo` → theme_mod; others → option)

### Known limits

Not yet stageable (documented out of scope for 0.5.0):
- Theme switching
- Classic widgets / legacy sidebars
- Classic (non-block) menu editor objects — use block `wp_navigation` instead
- Attachment post staging (by design — uploads are live immediately)

See [BUILD.md](BUILD.md) for ability details and [readme.txt](readme.txt) for complete documentation.

## Setup

**Requirements**: Hosted WordPress 6.9+ (not Playground)

1. Install and activate **Changesets** and **WordPress MCP Adapter** on your WordPress site
2. Create an Application Password (propose-only user without publish permissions recommended)
3. Connect your MCP client to the site using the Application Password

## What's new in 0.5.0

**Expanded staging capabilities** — agents can now stage essentially everything a block-theme site owner would edit:

- **Featured images**: Pass `featured_media` (attachment ID) when staging content
- **Site logo**: Stage `custom_logo` (theme_mod) with `type=setting`
- **Site icon**: Stage `site_icon` (option) with `type=setting`
- **Theme mod support**: Auto-detect `custom_logo` or explicitly set `store=theme_mod`
- **Media policy documented**: Uploads persist immediately; changesets stage only references (IDs)

See [readme.txt](readme.txt) for full changelog.

## License

GPLv2 or later
