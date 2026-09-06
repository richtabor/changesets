# Draft Changes — build spec (v1)

Lean WordPress plugin: agents propose edits to published posts/pages without touching live content. Humans review in Gutenberg and apply.

## Product principle

Don't give an agent permission to change production when you can give it permission to propose a change instead.

## Requirements

- WordPress ≥ 6.9 (Abilities API in core)
- Posts and Pages only in v1
- One open proposal per source post
- No settings dashboard
- Demo with [MCP Adapter](https://github.com/WordPress/mcp-adapter) installed separately (not a dependency). WebMCP optional, same story.

## Content model

Clone-as-draft (not native revisions):

1. `create-proposed-revision` clones a published post/page into a same-type `draft`
2. Meta on proposal: `_dcp_source_id` → source post ID; `_dcp_is_proposal` = `1`
3. Meta on source (optional): `_dcp_open_proposal_id` → enforce one-open rule
4. Agent edits only the proposal draft
5. Human **Apply to live** copies title/content/excerpt (+ featured image, categories, tags in v1) onto the source, creates a native revision of the source for undo, then trashes the proposal
6. Block `publish` / public visibility of proposals (force draft/pending; no public URL)

Native `post_type=revision` is history after Update — wrong tool for parallel drafts.

## Abilities (`draft-changes/`)

Category: `content-proposals`

| Ability | Who | Notes |
|---|---|---|
| `create-proposed-revision` | agent | `{ source_post_id }` → `{ proposal_id, source_post_id, edit_url, status }` |
| `update-proposed-revision` | agent | `{ proposal_id, title?, content?, excerpt? }` |
| `list-proposals` | agent | `{ source_post_id?, status? }` |
| `get-proposal` | agent | proposal + source link + preview |
| Apply to live | **human only** | Gutenberg sidebar / row action — not registered as a public Ability in v1 |

Register with clear descriptions (agents read these). Use `meta.show_in_rest` / `meta.public` as appropriate for discovery. Annotate create/update as non-destructive (additive drafts).

## Permissions

Propose-only Application Password / role:

- Allow: `read`, `edit_posts`, `edit_pages` (as needed), optional `upload_files`
- Deny: `publish_posts`, `edit_published_posts`, `delete_published_posts` (and page equivalents)
- Custom caps: `create_content_proposals`, `edit_content_proposals`, `apply_content_proposals` (human)

Every Ability `permission_callback` must verify caps **and** that writes target a proposal draft, never a published source.

## Human UI (minimal)

- On published post editor: notice if an open proposal exists + link to open it
- On proposal editor: banner “Proposal for: {title}” + **Apply to live** button
- Posts/Pages list: filter or badge for proposals

No SEO score, no giant queue product. A badge + Apply is enough for MVP.

## Out of scope (v1)

- Custom fields / ACF / Yoast meta merge
- Scheduling proposals
- Multiple concurrent proposals per post
- Block Notes / RTC collaborative suggestions (separate experiment)
- Bundling MCP Adapter or WebMCP

## Demo script

1. Activate plugin on WP 6.9+ site; install MCP Adapter for agent transport
2. From ChatGPT/Claude: “Simplify the homepage copy and make the CTA clearer, but don’t publish anything.”
3. Agent discovers Ability → creates proposal → updates content
4. Open proposal in Gutenberg → review → Apply to live
5. Live URL updated; proposal trashed; native revision available for undo

## Risks

- Accidental public publish of the clone → must hard-block
- Incomplete merge (template, parent, plugin meta) → document limits; expand later
- Cap leakage if agent user is Editor → document propose-only setup
- Preview/permalink confusion vs live URL
- Exposing Apply as a public Ability would break the product principle

## Public story (when ready)

build useful primitive → expose Abilities → use myself → release to plugin directory → demonstrate → write about what it suggests for WordPress
