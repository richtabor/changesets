# Draft Changes — build spec (changeset)

Lean WordPress plugin: agents and humans accumulate unpublished site edits in a **Changeset**, preview them on the real site without touching live, then **Publish Changeset**.

## Naming (locked)

| Term | Meaning |
|---|---|
| **Changeset** | Top-level staging session (the product object) |
| **Preview Changeset** | View the site with this changeset overlaid |
| **Approve Changeset** | Human marks the changeset ready for publish |
| **Publish Changeset** | Apply all ops in the changeset to live, then close it |
| Op / entity draft | Internal: a staged clone of a page, template, nav, etc. — not user-facing jargon |

Do **not** use “proposal”, “staging site”, or “Apply to live” as primary UX labels. Keep Ability/API names under `draft-changes/`.

## Product principle

Don't give an agent permission to change production when you can give it permission to propose a change instead.

Humans ask for **site outcomes** (“add Contact to the nav”, “warm up the colors”, “update About”). They should not need to know pages vs templates vs global styles vs navigation.

## Requirements

- WordPress ≥ 6.9 (Abilities API in core)
- Block theme entities preferred (FSE): pages/posts, `wp_template`, `wp_template_part`, `wp_global_styles`, `wp_navigation`
- One **open** changeset per site for v1 (session bucket); expand later if needed
- MCP Adapter installed separately (dogfood transport). Test as an MCP agent would — Abilities only, not SSH.
- No giant dashboard

## Architecture (WordPress-forward)

Inspired by Customizer **changesets** (`customize_changeset` + preview UUID), adapted to block-theme entities (which are already posts).

### 1. CPT `dcp_changeset`

- `post_title` = human label (“Add Contact”, “Home copy pass”)
- Status: `draft` (open) → `pending` (approved) → published/closed via meta after Publish Changeset (or trash on discard)
- Meta:
  - `_dcp_changeset_uuid` — public preview token
  - `_dcp_changeset_status` — `open` | `approved` | `published` | `discarded`
  - `_dcp_approved_by`, `_dcp_approved_at` when approved

### 2. Staged entity drafts (ops)

Everything visitor-facing that is already a WP post type gets a **draft clone** tagged into the changeset:

| Entity | post_type | Meta on clone |
|---|---|---|
| Page / post | `page` / `post` | `_dcp_changeset_id`, `_dcp_source_id`, `_dcp_is_staged` |
| Template | `wp_template` | same |
| Template part | `wp_template_part` | same |
| Global styles | `wp_global_styles` | same |
| Navigation | `wp_navigation` | same |

v1 implement **page/post content** fully; scaffold hooks for the FSE types so Preview/Publish can grow without renaming.

One open staged draft per source entity per changeset.

### 3. Preview Changeset

- Enter: `?dcp_changeset=<uuid>` **or** admin “Preview Changeset”
- Set cookie `dcp_changeset=<uuid>` so clicks stay in preview
- Admin bar banner: **Viewing changeset — Exit | Publish Changeset** (if approved / can publish)
- Filters overlay staged drafts over live queries (content, later templates/styles/nav)
- Hard rule: preview never writes to live entities

### 4. Publish Changeset

For each staged draft in the changeset:

1. Native revision of live source (undo)
2. Copy staged fields onto live source
3. Permanently delete staged draft

Then mark changeset `published` and clear preview cookie.

**Approve Changeset** is required before agent **Publish Changeset** (human gate). Human may Publish Changeset from UI without a separate step if they are the publisher.

## Abilities (`draft-changes/`)

Category: `content-proposals` (rename category label to “Changesets” in UI strings)

| Ability | Notes |
|---|---|
| `create-changeset` | `{ title? }` → `{ changeset_id, uuid, preview_url, status }` |
| `get-changeset` | changeset + list of staged entity summaries |
| `list-changesets` | open/approved |
| `stage-content` | `{ changeset_id, source_post_id }` — clone page/post into changeset |
| `create-staged-page` | `{ changeset_id, title, content?, slug? }` — brand new page in changeset |
| `update-staged-content` | `{ staged_id, title?, content?, excerpt? }` |
| `stage-setting` | `{ changeset_id, key, value }` — stage options (homepage, site title, etc.) |
| `stage-global-styles` | `{ changeset_id, settings?, styles? }` — stage theme.json edits |
| `stage-style-variation` | `{ changeset_id, variation }` — apply theme style variation |
| `approve-changeset` | human approval gate |
| `publish-changeset` | requires approved (for agents); applies all ops |

## Permissions

- Stage content: `manage_changesets` capability (Administrator / Editor)
- Approve Changeset: `approve_changesets` capability
- Publish Changeset: `publish_changesets` capability

## Human UI (minimal)

- Admin bar when previewing
- Changeset edit screen: list of staged items, Preview Changeset, Approve Changeset, Publish Changeset
- On staged page editor: banner “Part of changeset: {title}” + links

## Out of scope (this spike)

- Full Atomic duplicate staging environments
- Multi-open changesets / branching UX
- ACF / Yoast meta merge
- Collaborative RTC

## Demo script (MCP — no SSH)

1. `create-changeset` “Home copy”
2. `stage-content` for Home → `update-staged-content` (heading change)
3. Open `preview_url` — see change; Exit — see live unchanged
4. Human: Approve Changeset (ability or UI)
5. `publish-changeset` — live updates; staged drafts gone

## Success criteria

- [x] CPT + uuid + open changeset works
- [x] Stage + update page content without changing live
- [x] Preview via URL param + cookie; admin bar; exit restores live view
- [x] Approve + Publish Changeset abilities work over MCP Adapter
- [x] Live URL only changes after Publish Changeset
- [x] BUILD.md matches shipping code
