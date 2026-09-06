=== Draft Changes ===
Contributors: richtabor
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Propose edits to published posts and pages without changing the live version. Built for humans and agents (WordPress Abilities API).

== Description ==

Draft Changes gives WordPress a middle permission state:

Agent proposes → human reviews → human applies.

When an agent (or a person) needs to update a live page, the plugin creates a draft proposal linked to the published post. The live URL stays unchanged until someone reviews the proposal in the block editor and clicks **Apply to live**.

Exposes Abilities (`draft-changes/create-proposed-revision`, `update-proposed-revision`, `list-proposals`, `get-proposal`) so compatible agents can discover the workflow. Publishing remains human-only.

Pair with the WordPress MCP Adapter (separate plugin) if you want desktop/API agents to call these Abilities. WebMCP is optional for browser agents.

== Installation ==

1. Install on WordPress 6.9+.
2. Activate Draft Changes.
3. (Optional) Install MCP Adapter for agent transport.
4. Use a propose-only Application Password user without `publish_posts` / `edit_published_posts`.

== Changelog ==

= 0.1.0 =
* Initial scaffold: clone-as-draft proposals, Abilities, Apply to live, publish guard.
