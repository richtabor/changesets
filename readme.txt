=== Changesets ===
Contributors: richtabor
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accumulate site edits in a staging Changeset, preview them on the live site without touching production, then Publish Changeset after human approval. Built for AI agents and humans using the WordPress Abilities API.

== Description ==

Changesets introduces staging sessions where agents and humans accumulate site edits before publishing.

**Workflow**: Agent stages edits → human previews live site with overlay → human approves → Publish Changeset applies all changes.

The live site stays untouched until Publish. Preview shows exactly what visitors will see after publish.

Exposes Abilities (`changesets/create`, `changesets/stage-page`, `changesets/approve`, `changesets/publish`) so compatible agents can discover the workflow via the Abilities API.

Pair with the WordPress MCP Adapter (separate plugin) for desktop/API agent access.

== Installation ==

1. Install on WordPress 6.9+.
2. Activate Changesets.
3. (Optional) Install MCP Adapter for agent transport.
4. Use a propose-only Application Password user without `publish_posts` / `edit_published_posts`.

== Changelog ==

= 0.3.0 =
* Breaking: Product rename to Changesets (plugin name, text domain, user-facing strings).
* Breaking: Ability namespace changed from `draft-changes/*` to `changesets/*`.
* Breaking: CPT renamed from `dcp_changeset` to `changeset`.
* Breaking: Function prefixes changed from `dcp_*` to `cs_*`.
* Breaking: Post meta keys changed from `_dcp_*` to `_changeset_*`.
* Breaking: Exit query param changed from `dcp_exit_preview` to `exit_changeset`.
* Breaking: Simplified to 6 lean abilities: create, get, list, stage-page, approve, publish.
* Change: `changesets/stage-page` unifies stage-content, create-staged-page, and update-staged-content.
* Removed: update-changeset, stage-setting, stage-global-styles, stage-style-variation abilities.
* Note: No backward compatibility provided (unreleased experiment).

= 0.2.20 =
* Breaking: Remove legacy `dcp_changeset` query parameter and cookie support (no longer accepted).

= 0.2.19 =
* Change: Rename preview query parameter from `dcp_changeset` to `changeset` (backward compatible).

= 0.2.18 =
* Fix: Register ability category on wp_abilities_api_categories_init hook.

= 0.2.17 =
* Changeset architecture: staging sessions for site edits.
* Stage content, global styles, style variations, settings.
* Preview overlay on live site with admin bar.
* Approve and Publish Changeset workflow.
* Abilities API for agent integration.
