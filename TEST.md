# Changesets — Testing Guide

Manual and MCP smoke tests for the Changeset architecture.

## Prerequisites

- WordPress ≥ 6.9 with Abilities API
- Changesets plugin active (v0.2.0+)
- At least one published page or post
- User with `apply_content_proposals` capability (Administrator or Editor)
- MCP Adapter installed for MCP tests

## Manual UI Testing

### 1. Create Changeset

1. Navigate to **Changesets** in WordPress admin
2. Click **Add New Changeset**
3. Enter title (e.g., "Home page update")
4. **Verify**: Changeset created with UUID

### 2. Stage Content

Since staging is currently MCP-only in this version, you can verify staged content by:

1. Use MCP to stage content (see MCP tests below), or
2. Directly create a staged draft in the database for testing

### 3. Preview Changeset

1. Get the preview URL from changeset edit screen
2. Click **Preview Changeset** button
3. **Verify**: 
   - Admin bar shows "Viewing changeset: [title]"
   - Admin bar has "Exit Preview" and optionally "Publish Changeset" links
   - Cookie `changeset` is set
4. Navigate site — **Verify**: stayed in preview (cookie works)
5. Click **Exit Preview**
6. **Verify**: Back to live view, cookie cleared

### 4. Approve & Publish

1. Open changeset with staged content
2. Click **Approve Changeset** button
3. **Verify**: Status changes to `approved`
4. Click **Publish Changeset** button
5. **Verify**:
   - Live content updated
   - Staged drafts deleted
   - Native revision saved on source post
   - Changeset status = `published`

## MCP Testing (via MCP Adapter)

### Setup

Ensure MCP Adapter is connected and authenticated to your WordPress site.

### Test Flow

```bash
# 1. Create changeset
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/create \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Home copy pass"}'

# Response:
# {
#   "changeset_id": 123,
#   "uuid": "a1b2c3d4-...",
#   "preview_url": "https://your-site.com/?changeset=a1b2c3d4-...",
#   "status": "open"
# }

# 2. Stage content (clone published post into changeset)
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/stage \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"source_post_id":5}'

# Response:
# {
#   "staged_id": 456,
#   "source_post_id": 5,
#   "edit_url": "..."
# }

# 3. Update staged content
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/stage \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"staged_id":456,"title":"Updated Heading","content":"<!-- wp:paragraph --><p>New content</p><!-- /wp:paragraph -->"}'

# Response:
# {
#   "staged_id": 456,
#   "modified_gmt": "2026-09-06T12:00:00"
# }

# 4. Preview (visit preview_url in browser)
# https://your-site.com/?changeset=a1b2c3d4-...
# Verify: See staged changes, admin bar shows preview notice

# 5. Get changeset with staged items
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/get \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123}'

# Response:
# {
#   "changeset_id": 123,
#   "uuid": "a1b2c3d4-...",
#   "title": "Home copy pass",
#   "status": "open",
#   "preview_url": "...",
#   "staged_items": [...]
# }

# 6. Approve changeset (human gate)
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/approve \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123}'

# Response:
# {
#   "changeset_id": 123,
#   "approved": true,
#   "preview_url": "..."
# }

# 7. Publish changeset (requires approved)
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/publish \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123}'

# Response:
# {
#   "applied_count": 1,
#   "source_ids": [5]
# }
```

### Verification After Publish

1. Visit source post live URL
2. **Verify**: Title and content reflect staged changes
3. Check post revisions
4. **Verify**: New revision saved before changeset publish
5. Check staged draft (post ID 456)
6. **Verify**: Hard deleted (404)

## List Changesets

```bash
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/list \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"open","per_page":10}'

# Response:
# {
#   "items": [
#     {
#       "changeset_id": 123,
#       "uuid": "...",
#       "title": "Home copy pass",
#       "status": "open",
#       "staged_count": 1,
#       "created_gmt": "..."
#     }
#   ],
#   "total": 1
# }
```

## Error Cases to Test

### Publish without approval

```bash
# Try publishing un-approved changeset
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/publish \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123}'

# Expected error:
# {
#   "code": "cs_not_approved",
#   "message": "A human must Approve Changeset before Publish Changeset.",
#   "data": { ... }
# }
```

### Stage already-staged content

```bash
# Try staging same source twice in same changeset
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/stage \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"source_post_id":5}'

# Expected error:
# {
#   "code": "cs_already_staged",
#   "message": "This content is already staged in this changeset.",
#   "data": { "staged_id": 456 }
# }
```

### Hard-block staged draft publish

1. Open staged draft in editor (post ID 456)
2. Try clicking **Publish** or updating status to `publish`
3. **Verify**: Status forced back to `draft`
4. **Verify**: Staged draft never becomes a public URL


## Success Checklist

- [ ] Create changeset via ability
- [ ] Stage content via ability
- [ ] Update staged content via ability
- [ ] Preview URL shows staged changes
- [ ] Admin bar appears in preview mode
- [ ] Exit preview restores live view
- [ ] Approve changeset via ability
- [ ] Publish changeset via ability
- [ ] Live content updated after publish
- [ ] Staged drafts deleted after publish
- [ ] Native revision saved
- [ ] Cannot publish un-approved changeset
- [ ] Staged drafts blocked from public publish
