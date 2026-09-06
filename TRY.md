# Draft Changes — Try It Now

Two ways to experience Draft Changes on WordPress Playground: as a human previewing changes, or as a disposable agent staging and publishing content.

## 🚀 One-Click Demo

**[Try Draft Changes on Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/richtabor/draft-changes/main/blueprint.json)**

Boots with:
- Draft Changes plugin active
- WordPress MCP Adapter installed (for ability discovery on hosted sites)
- Demo changeset: "Home Copy Update" with staged homepage changes and new Contact page
- Lands on Changesets admin list

---

## 👤 Path 1: Human Preview

Try the preview workflow yourself in the browser.

### What You'll Do

1. **Launch Playground** — Click the blueprint URL above. You'll land on the Changesets list.
2. **View the Changeset** — Click "Home Copy Update" to see the staged items.
3. **Preview the Changeset** — Click the **Preview Changeset** button at the top of the edit screen.
4. **See Staged Changes** — You're now viewing the site with the changeset overlaid. The homepage shows "New homepage copy. Clear CTA and value prop." The admin bar displays "Viewing changeset: Home Copy Update" with Exit and Publish links.
5. **Exit Preview** — Click **Exit Preview** in the admin bar to return to the live site.
6. **Compare Live** — Visit the homepage again (via "Visit Site"). You'll see the old copy: "Old homepage copy. Vague CTA." The staged changes haven't touched production yet.

### What You'll Feel

The live site stays frozen until you **Publish Changeset**. Preview shows exactly what visitors will see after publish — no separate staging URL, no guessing.

---

## 🤖 Path 2: Disposable Agent (No App Password, No Hosting)

Run an AI agent that stages, previews, and publishes changes on an ephemeral Playground site — nothing permanent, no authentication setup.

### Architecture

Disposable agent play uses **two separate MCP components**:

1. **WordPress MCP Adapter** (installed in the blueprint) — WordPress plugin that exposes Abilities as MCP tools on *hosted* sites (requires HTTP + auth). Installed in Playground so abilities are discoverable for future hosted dogfood workflows.

2. **`@wp-playground/mcp`** (npm package, local stdio MCP) — WebSocket bridge from your coding agent → browser Playground tab. **This is how agents drive Playground without an app password.** No HTTP, no authentication — just direct browser communication.

**Important**: Playground is ephemeral and browser-based. You cannot connect to it via Application Password or remote HTTP the way you would with a hosted site like Atomic. The `@wp-playground/mcp` bridge is the disposable agent path.

### Setup

1. **Install the Playground MCP bridge**:

   ```bash
   npx -y @wp-playground/mcp
   ```

   This starts a local MCP server that can open and control Playground browser tabs.

2. **Connect your agent** to the `@wp-playground/mcp` MCP server (stdio, usually auto-discovered in Cursor or Claude Desktop).

3. **Open the Playground blueprint** via the agent or manually using the one-click URL above. The agent may provide a Playground URL with `mcp-port` parameter for direct connection.

### Agent Workflow

Once connected, tell your agent:

> "The Playground site is ready at [Playground URL]. Use Draft Changes abilities to stage content, then I'll preview and approve."

#### Available Abilities

The agent discovers these via the Abilities API (exposed by the WordPress MCP Adapter and also usable through `@wp-playground/mcp` tools):

- `draft-changes/create-changeset` — Create a new staging session
- `draft-changes/stage-content` — Clone a published page/post into the changeset
- `draft-changes/create-staged-page` — Create a brand-new page in the changeset
- `draft-changes/update-staged-content` — Edit staged page content
- `draft-changes/approve-changeset` — **Human approval gate** (ability call, not an admin button)
- `draft-changes/publish-changeset` — Apply all staged changes to live (requires approved status)

#### Example Flow

1. **Agent stages edits**:
   ```
   Agent: I'll update the homepage copy and add a Contact page.
   [Calls stage-content and create-staged-page]
   ```

2. **Human previews**:
   - Agent provides the preview URL: `https://playground.wordpress.net/...?dcp_changeset=<UUID>`
   - You open it in the browser
   - You see the staged changes overlaid on the live site

3. **Human approves**:
   ```
   You: Looks good, approve it.
   Agent: [Calls approve-changeset]
   ```

4. **Agent publishes**:
   ```
   Agent: Approved. Publishing now.
   [Calls publish-changeset]
   Agent: Done. Refresh the live homepage to see the changes.
   ```

5. **Verify**:
   - Refresh the live site (without `?dcp_changeset=`)
   - Homepage now shows the staged copy
   - Staged drafts are deleted

### Preview URL Pattern

Preview URLs follow this format:

```
https://playground.wordpress.net/...?dcp_changeset=<UUID>
```

The `dcp_changeset` query parameter + cookie overlay the staged content. Exit preview by clicking **Exit Preview** in the admin bar or removing the query parameter.

### What You'll Feel

Agent stages → you open preview → you say approve → agent publishes → refresh to see live changes. The agent can execute the full workflow (including Approve + Publish via abilities) without you touching admin buttons — you just review the preview URL.

---

## Advanced: Hosted Dogfood Site (Optional)

If you want to test Draft Changes on a *hosted* WordPress site (e.g., Atomic, WP Engine) with persistent storage:

1. Install **Draft Changes** and **WordPress MCP Adapter** on the hosted site.
2. Create a propose-only Application Password user without `publish_posts` or `edit_published_posts` capabilities.
3. Connect your agent via MCP Adapter using the Application Password (HTTP + auth).
4. Use the same abilities workflow as the disposable path.

This path requires manual site setup and authentication — not disposable, but persistent and shareable.

---

## What's Included in the Demo

- **Live Homepage**: "Old homepage copy. Vague CTA."
- **Staged Homepage**: "New homepage copy. Clear CTA and value prop."
- **Staged Contact Page**: Brand-new page (not published yet)
- **Changeset**: "Home Copy Update" (status: open)

## Success

- Preview shows staged changes without touching live ✅
- Live site stays frozen until Publish Changeset ✅
- Agent can stage, approve, and publish via abilities ✅
- No permanent hosting, no app password needed for disposable path ✅

## Feedback

Try it, break it, ship it. Issues and PRs welcome at [github.com/richtabor/draft-changes](https://github.com/richtabor/draft-changes).
