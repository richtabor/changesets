# Changesets — build spec

WordPress plugin: agents and humans accumulate site edits in a **Changeset**, preview them on the real site without touching live, then **Publish Changeset**.

## Product goal

A changeset is a temporary version of the site. Agents can stage pages/posts/templates/template parts/navigation/global styles/site settings/logos/featured images — preview via `?changeset=` without touching live — then approve + publish. Like Customizer changesets for block themes.

## Version

**0.5.0** — Expanded staging capabilities: featured images, logos (custom_logo theme_mod), site_icon, theme_mod support.

## Naming (locked)

| Term | Meaning |
|---|---|
| **Changeset** | Top-level staging session (the product object) |
| **Preview Changeset** | View the site with this changeset overlaid |
| **Approve Changeset** | Human marks the changeset ready for publish |
| **Publish Changeset** | Apply all ops in the changeset to live, then close it |

Do **not** use "proposal", "staging site", or "Apply to live" as primary UX labels. Keep Ability/API names under `changesets/`.

## Product principle

Don't give an agent permission to change production when you can give it permission to propose a change instead.

Humans ask for **site outcomes** ("add Contact to the nav", "warm up the colors", "update About"). They should not need to know pages vs templates vs global styles vs navigation.

## Requirements

- WordPress ≥ 6.9 (Abilities API in core)
- Block theme entities preferred (FSE): pages/posts, `wp_template`, `wp_template_part`, `wp_navigation`, global styles
- One **open** changeset per site for v1 (session bucket); expand later if needed
- MCP Adapter installed separately (dogfood transport). Test as an MCP agent would — Abilities only, not SSH.
- No giant dashboard

### Media policy

**Attachments are never staged** — uploads go directly to the Media Library and persist there even if the changeset is discarded. Changesets stage only **references** to media:

- Content HTML/blocks with attachment IDs (written by editor)
- Featured images (`featured_media` field on content saves)
- Site logo (`custom_logo` theme_mod)
- Site icon (`site_icon` option)

When the changeset is published, the references (IDs) are applied to live content. When discarded, uploaded media remains in the library.

## Architecture (WordPress-forward)

Inspired by Customizer **changesets** (`customize_changeset` + preview UUID), adapted to block-theme entities (which are already posts).

### 1. CPT `changeset`

- `post_title` = human label ("Add Contact", "Home copy pass")
- Status: `draft` (open) → `pending` (approved) → published/closed via meta after Publish Changeset (or trash on discard)
- Meta:
  - `_changeset_uuid` — public preview token
  - `_changeset_status` — `open` | `approved` | `published` | `discarded`
  - `_changeset_approved_by`, `_changeset_approved_at` when approved
  - `_changeset_staged_options` — site settings bag
  - `_changeset_staged_global_styles` — global styles JSON
  - `_changeset_staged_style_variation` — style variation stem

### 2. Staged entity drafts (ops)

Everything visitor-facing that is a WP post type gets a **draft clone** tagged into the changeset:

| Entity | post_type | Meta on clone |
|---|---|---|
| Page / post | `page` / `post` | `_changeset_id`, `_changeset_source`, `_changeset_is_staged` |
| Template | `wp_template` | same |
| Template part | `wp_template_part` | same |
| Navigation | `wp_navigation` | same |
| Custom post types | any public CPT with `show_ui` | same |

One staged draft per source entity per changeset. Source ID = 0 means brand-new content (no live source).

### 3. Preview Changeset

- Enter: `?changeset=<uuid>` **or** admin "Preview Changeset"
- Set cookie `changeset=<uuid>` so clicks stay in preview
- Admin bar banner: **Viewing changeset — Exit | Publish Changeset** (if approved / can publish)
- Filters overlay staged drafts over live queries (content, templates, styles, settings, nav)
- Hard rule: preview never writes to live entities

### 4. Publish Changeset

For each staged draft in the changeset:

1. Native revision of live source (undo)
2. Copy staged fields onto live source
3. Permanently delete staged draft

For staged settings: apply to live options.

For staged global styles: apply to user global styles post.

Then mark changeset `published` and clear preview cookie.

**Approve Changeset** is required before agent **Publish Changeset** (human gate). Human may Publish Changeset from UI without a separate step if they are the publisher.

## Abilities (`changesets/`)

Category: `changesets` (label: "Changesets")

**Important**: The ability category must be registered on the `wp_abilities_api_categories_init` hook (before abilities are registered on `wp_abilities_api_init`). Category registration fails silently when called on the later `wp_abilities_api_init` hook.

| Ability | Notes |
|---|---|
| `changesets/create` | `{ title? }` → `{ changeset_id, uuid, preview_url, status }` |
| `changesets/get` | changeset + list of staged entity summaries |
| `changesets/list` | open/approved |
| **`changesets/save`** | **Unified staging ability (v0.4.0, expanded v0.5.0)**<br>`type=content`: stage pages/posts/templates/parts/navigation/CPTs (`post_type`, `source_id?`, `title?`, `content?`, `slug?`, `theme?`, `featured_media?`)<br>`type=styles`: stage global styles or variation (`variation?`, `styles?`, `settings?`)<br>`type=setting`: stage site option or theme_mod (`key`, `value`, `store?`) |
| `changesets/approve` | human approval gate |
| `changesets/publish` | requires approved (for agents); applies all ops |

### `changesets/save` examples

**Stage existing page:**
```json
{
  "changeset_id": 123,
  "type": "content",
  "source_id": 5
}
```

**Stage existing page with featured image:**
```json
{
  "changeset_id": 123,
  "type": "content",
  "source_id": 5,
  "featured_media": 42
}
```

**Create new page:**
```json
{
  "changeset_id": 123,
  "type": "content",
  "post_type": "page",
  "title": "Contact",
  "content": "<!-- wp:paragraph --><p>Get in touch</p><!-- /wp:paragraph -->"
}
```

**Create new page with featured image:**
```json
{
  "changeset_id": 123,
  "type": "content",
  "post_type": "page",
  "title": "Gallery",
  "content": "<!-- wp:paragraph --><p>Photos</p><!-- /wp:paragraph -->",
  "featured_media": 100
}
```

**Stage template:**
```json
{
  "changeset_id": 123,
  "type": "content",
  "post_type": "wp_template",
  "source_id": 42
}
```

**Apply style variation:**
```json
{
  "changeset_id": 123,
  "type": "styles",
  "variation": "twilight"
}
```

**Stage global styles:**
```json
{
  "changeset_id": 123,
  "type": "styles",
  "styles": {
    "color": {
      "palette": [...]
    }
  }
}
```

**Stage site setting (option):**
```json
{
  "changeset_id": 123,
  "type": "setting",
  "key": "blogname",
  "value": "My New Site Title"
}
```

**Stage site logo (theme_mod, auto-detected):**
```json
{
  "changeset_id": 123,
  "type": "setting",
  "key": "custom_logo",
  "value": 42
}
```

**Stage site icon (option):**
```json
{
  "changeset_id": 123,
  "type": "setting",
  "key": "site_icon",
  "value": 43
}
```

## Permissions

- Stage content: `manage_changesets` capability (Administrator / Editor)
- Approve Changeset: `approve_changesets` capability
- Publish Changeset: `publish_changesets` capability

## Human UI (minimal)

- Admin bar when previewing
- Changeset edit screen: list of staged items, Preview Changeset, Approve Changeset, Publish Changeset
- On staged entity editor: banner "Part of changeset: {title}" + links

## Out of scope

- Full Atomic duplicate staging environments
- Multi-open changesets / branching UX
- ACF / Yoast meta merge
- Collaborative RTC
- Playground support (own-site MCP only)
- **Theme switching** (0.5.0)
- **Classic widgets / legacy sidebars** (0.5.0)
- **Classic (non-block) menu editor objects** — use block `wp_navigation` instead (0.5.0)
- **Attachment post staging** — uploads persist immediately by design (0.5.0)

## Demo script (MCP — no SSH)

**Requires hosted WordPress** with Changesets + MCP Adapter + Application Password.

1. `changesets/create` "Home copy"
2. `changesets/save` type=content, source_id=Home
3. `changesets/save` type=content (edit title/content)
4. `changesets/save` type=content, featured_media=42 (set featured image)
5. `changesets/save` type=styles, variation=twilight
6. `changesets/save` type=setting, key=blogname, value="New Title"
7. `changesets/save` type=setting, key=custom_logo, value=50 (site logo)
8. Open `preview_url` — see changes; Exit — see live unchanged
9. Human: Approve Changeset (ability or UI)
10. `changesets/publish` — live updates; staged drafts gone; media persists

## Success criteria

- [x] CPT + uuid + open changeset works
- [x] Stage content (pages/posts/templates/parts/navigation/CPTs) via unified `changesets/save` ability
- [x] Stage featured images via `featured_media` field (0.5.0)
- [x] Stage global styles and style variations via `changesets/save` ability
- [x] Stage site settings (options) via `changesets/save` ability
- [x] Stage theme_mods (custom_logo) via `changesets/save` ability (0.5.0)
- [x] Stage site_icon via `changesets/save` ability (0.5.0)
- [x] Preview via URL param + cookie; admin bar; exit restores live view
- [x] Approve + Publish Changeset abilities work over MCP Adapter
- [x] Live URL only changes after Publish Changeset
- [x] Media uploads persist immediately; discarding changeset keeps media in library (0.5.0)
- [x] BUILD.md matches shipping code
