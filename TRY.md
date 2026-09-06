# Try Draft Changes in WordPress Playground

Experience Draft Changes instantly in your browser with WordPress Playground — no installation required.

## One-Click Demo

Click to launch:

**https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/richtabor/draft-changes/main/blueprint.json**

The demo will:
1. Install WordPress with PHP 8.2 and the latest version
2. Install and activate Draft Changes from the main branch
3. Create a sample homepage with "live" content
4. Create a changeset titled "Try Draft Changes" containing:
   - Updated homepage with improved copy and a CTA button
   - A new "Contact Us" page (staged, not yet published)
5. Land you on the **Changesets admin page** where you can explore and preview the changes

## What to Try

### View the Changeset
- You'll land on **WP Admin → Changesets** showing the "Try Draft Changes" changeset
- Click the changeset title to view details
- See the two staged items:
  - **Welcome to Our Premium Store** (modified homepage)
  - **Contact Us** (new page)

### Preview the Changes
- Click **"Preview Changeset"** from the changeset detail page
- You'll see the homepage with the **staged** enhanced copy and "Shop Now" button
- Try navigating (the preview follows you via cookie)
- The Contact Us page won't be in navigation yet (it's only staged), but you can view it in the changeset

### Compare Live vs Staged
- In preview mode, click **"Exit Preview"** (or visit the site without the `?dcp_changeset=...` parameter)
- The homepage now shows the **live** version with the original simpler copy
- Return to preview mode from the admin to see the staged changes again

### Approve and Publish
- From the changeset screen, click **"Approve Changeset"** (human approval gate)
- Then click **"Publish Changeset"** to apply all changes to the live site
- The staged content is merged into live:
  - Homepage gets the premium copy and button
  - Contact Us page is created as a published page
- The changeset closes and the staged drafts are cleaned up

## Important Note: Public Repository Required

**Anonymous Playground Access:** The `git:directory` resource type requires a **public repository** for strangers to use this blueprint URL without authentication.

Currently, `richtabor/draft-changes` is private, so:
- ✅ The blueprint **will work for you** (the repo owner) if you're logged into GitHub
- ❌ Strangers **will see an error** when Playground tries to clone from the private repo

### Options for public sharing:

1. **Make the repository public** (when ready to share widely)
2. **Host a public `.zip` of the plugin:**
   - Upload a release `.zip` to a public URL
   - Change the blueprint to use:
     ```json
     {
       "resource": "url",
       "url": "https://example.com/draft-changes.zip"
     }
     ```
3. **Share the blueprint file itself** for local/authenticated use:
   - Users can download `blueprint.json` and upload it to playground.wordpress.net
   - Or use it with local Playground instances

## For Local/Authenticated Testing

You can test this blueprint now:

1. **Direct upload:** Download `blueprint.json` and drag it onto https://playground.wordpress.net
2. **GitHub authenticated:** If logged into GitHub with repo access, the URL above will work
3. **Local Playground:** Use with wp-now or other local Playground tools

---

**Built with [WordPress Playground](https://wordpress.github.io/wordpress-playground/)** — instant WordPress environments in your browser.

