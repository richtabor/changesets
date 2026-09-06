=== Draft Changes ===
Contributors: richtabor
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.2.19
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accumulate site edits in a staging Changeset, preview them on the live site without touching production, then Publish Changeset after human approval. Built for AI agents and humans using the WordPress Abilities API.

== Description ==

Draft Changes introduces Changesets: staging sessions where agents and humans accumulate site edits before publishing.

**Workflow**: Agent stages edits → human previews live site with overlay → human approves → Publish Changeset applies all changes.

The live site stays untouched until Publish. Preview shows exactly what visitors will see after publish.

Exposes Abilities (`draft-changes/create-changeset`, `stage-content`, `stage-global-styles`, `approve-changeset`, `publish-changeset`) so compatible agents can discover the workflow via the Abilities API.

Pair with the WordPress MCP Adapter (separate plugin) for desktop/API agent access.

== Try It Now ==

**One-Click Playground Demo**:
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/richtabor/draft-changes/main/blueprint.json

**Human Preview Path**: Open the blueprint, go to the Changesets admin list, click the demo changeset "Home Copy Update", then click Preview Changeset to see staged changes overlaid on the live site. The admin bar shows Exit and Publish links. Exit returns you to the live site — staged changes haven't touched production. The live site stays frozen until you Publish Changeset.

**Agent Workflow Notes**: Changesets list → Preview; Exit; live untouched; Approve/Publish are abilities not admin buttons.

== Agent Prompt ==

Paste this prompt to your AI agent to try Draft Changes on WordPress Playground with no hosting or Application Password:

```
You are helping me try Draft Changes, an experimental WordPress plugin for agentic staging (changesets): stage edits without touching live, preview on the real site, then approve + publish.

Goal: spin up a disposable WordPress Playground site, make real staged changes with the plugin, give me a preview link, wait for my approval, then publish. Nothing permanent. No Application Password. No hosted site.

Setup:
1. Connect the WordPress Playground MCP bridge if you don't already have it: run `npx -y @wp-playground/mcp` (stdio MCP). Give me the Playground URL that includes the mcp-port so I can keep the tab open if needed.
2. Open this blueprint (Draft Changes + MCP Adapter + a seeded demo changeset):
   https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/richtabor/draft-changes/main/blueprint.json

How to work:
- Do not edit published pages/options directly. Use Draft Changes abilities (discoverable via the Abilities API).
- Preview URLs include a `?changeset=<UUID>` query parameter (from get-changeset / create-changeset).
- Approve and publish are abilities — there are no admin Approve/Publish buttons. Call approve-changeset only after I say the preview looks good; then publish-changeset.

Do this now:
1. Open the blueprint and confirm Draft Changes abilities are available.
2. Either use the seeded demo changeset or create a new one and make a small visible edit (e.g. Home copy tweak and/or a new Contact page).
3. Send me the preview URL and a one-line summary of what's staged. Stop and wait.
4. When I say "approve", approve then publish, then tell me to refresh the live homepage without the query param to verify.

Repo/docs if you need them: https://github.com/richtabor/draft-changes
```

== Installation ==

1. Install on WordPress 6.9+.
2. Activate Draft Changes.
3. (Optional) Install MCP Adapter for agent transport.
4. Use a propose-only Application Password user without `publish_posts` / `edit_published_posts`.

== Changelog ==

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
