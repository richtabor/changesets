=== Draft Changes ===
Contributors: richtabor
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.2.18
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accumulate site edits in a staging Changeset, preview them on the live site without touching production, then Publish Changeset after human approval. Built for AI agents and humans using the WordPress Abilities API.

== Description ==

Draft Changes introduces Changesets: staging sessions where agents and humans accumulate site edits before publishing.

**Workflow**: Agent stages edits → human previews live site with overlay → human approves → Publish Changeset applies all changes.

The live site stays untouched until Publish. Preview shows exactly what visitors will see after publish.

Exposes Abilities (`draft-changes/create-changeset`, `stage-content`, `stage-global-styles`, `approve-changeset`, `publish-changeset`) so compatible agents can discover the workflow via the Abilities API.

Pair with the WordPress MCP Adapter (separate plugin) for desktop/API agent access.

== Installation ==

1. Install on WordPress 6.9+.
2. Activate Draft Changes.
3. (Optional) Install MCP Adapter for agent transport.
4. Use a propose-only Application Password user without `publish_posts` / `edit_published_posts`.

== Changelog ==

= 0.2.18 =
* Fix: Register ability category on wp_abilities_api_categories_init hook.

= 0.2.17 =
* Changeset architecture: staging sessions for site edits.
* Stage content, global styles, style variations, settings.
* Preview overlay on live site with admin bar.
* Approve and Publish Changeset workflow.
* Abilities API for agent integration.
