# Changesets — Testing Guide

Manual and MCP smoke tests for the Changeset architecture.

## Prerequisites

- WordPress ≥ 6.9 with Abilities API
- Changesets plugin active (v0.4.0+)
- At least one published page or post
- User with `manage_changesets` capability (Administrator or Editor)
- MCP Adapter installed for MCP tests

## Manual UI Testing

### 1. Create Changeset

1. Navigate to **Changesets** in WordPress admin
2. Click **Add New Changeset**
3. Enter title (e.g., "Home page update")
4. **Verify**: Changeset created with UUID

### 2. Preview Changeset

1. Get the preview URL from changeset edit screen
2. Click **Preview Changeset** button
3. **Verify**: 
   - Admin bar shows "Viewing changeset: [title]"
   - Admin bar has "Exit Preview" and optionally "Publish Changeset" links
   - Cookie `changeset` is set
4. Navigate site — **Verify**: stayed in preview (cookie works)
5. Click **Exit Preview**
6. **Verify**: Back to live view, cookie cleared

### 3. Approve & Publish

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

### Test Flow: Content Staging

```bash
# 1. Create changeset
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/create \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Full site staging test"}'

# Response:
# {
#   "changeset_id": 123,
#   "uuid": "a1b2c3d4-...",
#   "preview_url": "https://your-site.com/?changeset=a1b2c3d4-...",
#   "status": "open"
# }

# 2. Stage existing page (clone)
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/save \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"type":"content","source_id":5}'

# Response:
# {
#   "staged_id": 456,
#   "changeset_id": 123,
#   "type": "content",
#   "source_id": 5,
#   "post_type": "page",
#   "title": "...",
#   "edit_url": "..."
# }

# 3. Update staged page content
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/save \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"type":"content","source_id":5,"title":"Updated Title","content":"<!-- wp:paragraph --><p>New content</p><!-- /wp:paragraph -->"}'

# 4. Create brand new page (no source)
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/save \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"type":"content","post_type":"page","title":"Contact","content":"<!-- wp:paragraph --><p>Get in touch</p><!-- /wp:paragraph -->","slug":"contact"}'

# Response:
# {
#   "staged_id": 457,
#   "changeset_id": 123,
#   "type": "content",
#   "source_id": 0,
#   "post_type": "page",
#   "title": "Contact",
#   "slug": "contact",
#   "preview_path": "/contact/?changeset=..."
# }
```

### Test Flow: Styles Staging

```bash
# 1. Apply style variation
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/save \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"type":"styles","variation":"twilight"}'

# Response:
# {
#   "changeset_id": 123,
#   "type": "styles",
#   "stem": "05-twilight",
#   "title": "Twilight",
#   "staged": true
# }

# 2. Stage global styles (custom colors)
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/save \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"type":"styles","styles":{"color":{"palette":[{"slug":"primary","color":"#ff0000","name":"Primary"}]}}}'

# Response:
# {
#   "changeset_id": 123,
#   "type": "styles",
#   "staged": true,
#   "styles": { ... }
# }
```

### Test Flow: Settings Staging

```bash
# 1. Stage site title
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/save \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"type":"setting","key":"blogname","value":"New Site Title"}'

# Response:
# {
#   "changeset_id": 123,
#   "type": "setting",
#   "key": "blogname",
#   "value": "New Site Title",
#   "staged": true
# }

# 2. Stage homepage setting
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/save \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"type":"setting","key":"show_on_front","value":"page"}'

# 3. Set front page (if setting show_on_front=page)
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/save \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"type":"setting","key":"page_on_front","value":5}'
```

### Preview Testing

```bash
# Visit preview URL in browser
# https://your-site.com/?changeset=a1b2c3d4-...

# Verify:
# - See all staged changes (content, styles, settings)
# - Admin bar shows preview notice
# - Site title reflects staged setting
# - Colors reflect staged styles
# - Content shows staged edits

# Get changeset with all staged items
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/get \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123}'

# Response:
# {
#   "changeset_id": 123,
#   "uuid": "a1b2c3d4-...",
#   "title": "Full site staging test",
#   "status": "open",
#   "preview_url": "...",
#   "staged_items": [...]
# }
```

### Approve and Publish

```bash
# 1. Approve changeset (human gate)
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

# 2. Publish changeset (requires approved)
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/publish \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123}'

# Response:
# {
#   "changeset_id": 123,
#   "applied_count": 2,
#   "published_new_count": 1,
#   "source_ids": [5, 457],
#   "status": "published"
# }
```

### Verification After Publish

1. Visit affected pages live (without changeset query param)
2. **Verify**: Content reflects staged changes
3. **Verify**: Site title shows staged value
4. **Verify**: Styles/colors reflect staged global styles
5. Check post revisions
6. **Verify**: New revision saved before changeset publish
7. Check staged drafts
8. **Verify**: Hard deleted (404)
9. Check wp_options table
10. **Verify**: blogname and other settings updated
11. Check wp_global_styles post
12. **Verify**: post_content has staged styles JSON

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
#       "title": "Full site staging test",
#       "status": "open",
#       "staged_count": 3,
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
#   "data": { "changeset_id": 123, "preview_url": "..." }
# }
```

### Stage already-staged content

```bash
# Try staging same source twice in same changeset
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/save \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"type":"content","source_id":5}'

# Expected error:
# {
#   "code": "cs_already_staged",
#   "message": "This content is already staged in this changeset.",
#   "data": { "staged_id": 456 }
# }
```

### Invalid type

```bash
# Try using invalid type
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/save \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"type":"invalid"}'

# Expected error:
# {
#   "code": "cs_invalid_type",
#   "message": "Invalid type. Must be content, styles, or setting."
# }
```

### Unsupported post type

```bash
# Try staging unsupported post type
curl -X POST https://your-site.com/wp-json/wp/v2/abilities/changesets/changesets/save \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"changeset_id":123,"type":"content","post_type":"attachment"}'

# Expected error:
# {
#   "code": "cs_unsupported_type",
#   "message": "Post type \"attachment\" is not stageable."
# }
```

### Hard-block staged draft publish

1. Open staged draft in editor
2. Try clicking **Publish** or updating status to `publish`
3. **Verify**: Status forced back to `draft`
4. **Verify**: Staged draft never becomes a public URL

## Critical Test Cases (v0.4.2+)

### New page as homepage (publish + setting remap)

**Bug fixed in 0.4.2**: New staged pages (source_id=0) set as homepage via `page_on_front` were left as drafts, causing 404.

**Test**:
1. Create changeset
2. Create new page via `changesets/save` type=content (no source_id):
   ```json
   {"changeset_id": 123, "type": "content", "post_type": "page", "title": "Welcome", "content": "..."}
   ```
   Response: `{"staged_id": 456, ...}`
3. Stage homepage settings:
   ```json
   {"changeset_id": 123, "type": "setting", "key": "show_on_front", "value": "page"}
   {"changeset_id": 123, "type": "setting", "key": "page_on_front", "value": 456}
   ```
4. Approve and publish changeset
5. **Verify**:
   - Page 456 has `post_status = 'publish'` (not draft)
   - Option `page_on_front` equals 456 (staged ID remapped correctly to final live ID)
   - Homepage loads at `/` without 404
   - Page no longer has `_changeset_is_staged` meta

**Why this matters**: Settings that reference staged content must be remapped to final IDs after content is published, not applied with staged IDs.

### Discard changeset (new in 0.4.2)

**Test**:
1. Create changeset
2. Stage some content
3. Call `changesets/discard`:
   ```json
   {"changeset_id": 123}
   ```
4. **Verify**:
   - Changeset post status is `trash`
   - Changeset meta `_changeset_status` = `discarded`
   - All staged drafts are permanently deleted
   - Preview cookie is cleared
   - Response includes `deleted_count`

### Status check (new in 0.4.2)

**Test**:
1. Call `changesets/status` (no parameters)
2. **Verify** response includes:
   - `version` (e.g. "0.4.2")
   - `abilities_registered` (true when Abilities API available)
   - `user_caps` object with `manage_changesets`, `approve_changesets`, `publish_changesets`
   - `open_changeset_count`

**Use case**: Agents should call this before `create` to verify setup is complete.

## Success Checklist

- [ ] Create changeset via ability
- [ ] Stage content (pages, posts, templates, etc) via `changesets/save` type=content
- [ ] Stage global styles via `changesets/save` type=styles
- [ ] Stage style variation via `changesets/save` type=styles
- [ ] Stage settings via `changesets/save` type=setting
- [ ] Preview URL shows all staged changes
- [ ] Admin bar appears in preview mode
- [ ] Exit preview restores live view
- [ ] Approve changeset via ability
- [ ] Publish changeset via ability
- [ ] Live content updated after publish
- [ ] Live settings updated after publish
- [ ] Live styles updated after publish
- [ ] Staged drafts deleted after publish
- [ ] Native revision saved
- [ ] Cannot publish un-approved changeset
- [ ] Staged drafts blocked from public publish
