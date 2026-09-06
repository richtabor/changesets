# Draft Changes — Test MCP Flow

## Changeset Workflow (v0.2.1+)

### Create Contact Page in Changeset

Test the complete flow: create a changeset, add a brand-new Contact page, preview it appearing in the navigation, approve, and publish.

```jsonc
// 1. Create a changeset
{
  "ability": "draft-changes/create-changeset",
  "input": {
    "title": "Add Contact Page"
  }
}
// Returns: { changeset_id, uuid, preview_url, status: "open" }

// 2. Create a new Contact page (staged, not live)
{
  "ability": "draft-changes/create-staged-page",
  "input": {
    "changeset_id": <changeset_id>,
    "title": "Contact",
    "content": "<!-- wp:paragraph --><p>Get in touch with us!</p><!-- /wp:paragraph -->",
    "slug": "contact"
  }
}
// Returns: { staged_id, title, slug, edit_url, preview_path }

// 3. Get changeset to review staged items
{
  "ability": "draft-changes/get-changeset",
  "input": {
    "changeset_id": <changeset_id>
  }
}
// Returns: { changeset_id, title, uuid, status, preview_url, staged_items: [...] }

// 4. Preview: Visit preview_url
// - Contact page should appear in navigation (via core/page-list)
// - /contact/ should resolve and show content
// - Exit preview: live site unchanged (no Contact page yet)

// 5. Human: Approve changeset (via UI or ability)
{
  "ability": "draft-changes/approve-changeset",
  "input": {
    "changeset_id": <changeset_id>
  }
}
// Returns: { changeset_id, approved: true }

// 6. Agent: Publish changeset
{
  "ability": "draft-changes/publish-changeset",
  "input": {
    "changeset_id": <changeset_id>
  }
}
// Returns: { changeset_id, applied_count, published_new_count }

// 7. Verify: Live site now has Contact page
// - Contact appears in navigation permanently
// - /contact/ resolves on live site
```

## Stage Existing Content

```jsonc
// 1. Get or create open changeset
{
  "ability": "draft-changes/create-changeset",
  "input": {
    "title": "Update Home"
  }
}

// 2. Stage existing published page
{
  "ability": "draft-changes/stage-content",
  "input": {
    "changeset_id": <changeset_id>,
    "source_post_id": <home_page_id>
  }
}
// Returns: { staged_id, source_post_id, edit_url }

// 3. Update staged content
{
  "ability": "draft-changes/update-staged-content",
  "input": {
    "staged_id": <staged_id>,
    "title": "Welcome Home - Updated",
    "content": "<!-- wp:heading --><h2>New heading</h2><!-- /wp:heading -->"
  }
}

// 4. Preview, approve, publish as above
```

## Expected Behavior

- **Preview mode**: Staged pages appear published (status, slug, in page-list), but live is untouched
- **Publish**: New pages become real published pages; existing pages merge onto sources
- **Navigation**: `core/page-list` block automatically includes Contact once published
- **Rollback**: Native WP revisions saved for source merges

## Site Context (Dogfood)

- Theme: Twenty Twenty-Five
- Navigation (ID 3): `<!-- wp:page-list /-->` (auto-includes published pages)
- No manual nav editing needed for first cut
