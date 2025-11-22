# Migration Guide: Organizations to Committees

This guide explains the changes made to convert Organizations to Committees and enable multiple committee memberships for board members.

## Database Changes

### 1. Run the Migration

**Option 1: SQL Migration (Recommended)**
```bash
mysql -u root -p governance_board < database/migration_organizations_to_committees.sql
```

**Option 2: Fresh Install**
If starting fresh, use the updated `database/schema.sql` which already includes the committee structure.

### 2. Key Schema Changes

- `organizations` table renamed to `committees`
- `board_members` table no longer has:
  - `organization_id` (removed)
  - `role` (moved to `committee_members`)
  - `start_date`, `end_date`, `status` (moved to `committee_members`)
- New `committee_members` junction table for many-to-many relationship
- `meetings.organization_id` → `meetings.committee_id`
- `documents.organization_id` → `documents.committee_id`

## API Changes

### New Endpoints
- `api/committees.php` - Replaces `api/organizations.php`
- `api/committee_members.php` - Manages committee memberships

### Updated Endpoints
- `api/members.php` - Now supports `committee_id` parameter instead of `organization_id`
- `api/meetings.php` - Uses `committee_id` instead of `organization_id`
- `api/dashboard.php` - Uses `committee_id` instead of `organization_id`

## Frontend Changes

All references to "Organization" have been changed to "Committee" in:
- `index.php` - Dashboard
- `members.php` - Board Members page
- `meetings.php` - Meetings page
- `resolutions.php` - Resolutions page
- `export/agenda.php` - Agenda export

## Member Management Changes

### Before
- Members belonged to one organization
- Role, status, dates stored on member record

### After
- Members can belong to multiple committees
- Each membership has its own role, status, and dates
- Use `api/committee_members.php` to manage memberships

## Example: Adding a Member to Multiple Committees

```javascript
// Create member
const member = await fetch('api/members.php', {
    method: 'POST',
    body: JSON.stringify({
        first_name: 'John',
        last_name: 'Doe',
        email: 'john@example.com'
    })
}).then(r => r.json());

// Add to first committee
await fetch('api/committee_members.php', {
    method: 'POST',
    body: JSON.stringify({
        committee_id: 1,
        member_id: member.id,
        role: 'Chair',
        status: 'Active'
    })
});

// Add to second committee
await fetch('api/committee_members.php', {
    method: 'POST',
    body: JSON.stringify({
        committee_id: 2,
        member_id: member.id,
        role: 'Member',
        status: 'Active'
    })
});
```

## Backward Compatibility

The old `api/organizations.php` file still exists but should be replaced with `api/committees.php`. Update all frontend code to use the new endpoint.

## Testing Checklist

- [ ] Run database migration
- [ ] Verify committees can be created
- [ ] Verify members can be added to multiple committees
- [ ] Verify meetings are linked to committees
- [ ] Verify dashboard shows committee statistics
- [ ] Verify agenda export works with committees
- [ ] Test member role assignment per committee

