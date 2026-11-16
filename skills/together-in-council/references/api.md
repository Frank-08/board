# Together in Council API reference (for the together-in-council skill)

All endpoints live at `<base URL>/api/<name>.php`, take and return JSON, and
need the `X-API-Key` header. Writes use the body for ids, e.g. `PUT {"id": 5,
...}` / `DELETE {"id": 5}`. "Perm" is the permission (from `whoami.php`) that a
write needs; every read needs only the matching `view_*` permission.

## Identity

| Call | Returns |
|---|---|
| `GET whoami.php` | `{user: {id, username, email, role, board_member_id}, permissions: {action: bool}}` |

## Reference data

| Call | Notes |
|---|---|
| `GET meeting_types.php` | all meeting types (councils/committees), ordered |
| `GET meeting_types.php?id=N` | one meeting type |
| `GET members.php?meeting_type_id=N[&status=Active]` | members of a body; contacts redacted without `view_member_contacts` |
| `GET members.php?id=N` | one member |

Member `status`: `Active`, `Inactive`, `Resigned`, `Terminated`.

## Meetings

| Call | Notes |
|---|---|
| `GET dashboard.php[?meeting_type_id=N]` | `active_members`, `upcoming_meetings`, `recent_meetings`, `pending_resolutions`, `draft_minutes`, `upcoming_meetings_list`, `recent_meetings_list` |
| `GET meetings.php?meeting_type_id=N[&status=S][&limit=L]` | newest first. One of `id` / `meeting_type_id` is required |
| `GET meetings.php?id=N` | one meeting, incl. `quorum_required`, `quorum_met` |

Meeting `status`: `Scheduled`, `In Progress`, `Completed`, `Cancelled`, `Postponed`.
Creating/editing meetings needs `create_meeting`/`edit_meeting` (Clerk/Admin).

## Agenda

| Call | Notes |
|---|---|
| `GET agenda.php?meeting_id=M` | ordered items; `parent_id` set on sub-items |
| `GET agenda.php?id=N` | one item |

`item_type`: `Discussion`, `Action Item`, `Vote`, `Information`, `Presentation`.
`decision_method`: `Consensus`, `Formal Majority`, `Referral`, `None`.
`report_type`: `Written`, `Verbal`. Editing needs `manage_agenda`; reordering is
one `POST {"action": "reorder", "meeting_id": M, "order": [ids...]}`.

## Attendance

| Call | Notes |
|---|---|
| `GET attendees.php?meeting_id=M` | attendance rows |

`attendance_status`: `Present`, `Absent`, `Apology`, `Excused`, `Late`.
`edit_own_attendance` lets a member set their own row; `manage_attendees` any row.

## Minutes

| Call | Perm | Notes |
|---|---|---|
| `GET minutes.php?meeting_id=M` / `?id=N` | | |
| `POST minutes.php` | `draft_minutes` | `{meeting_id, content, status: "Draft", action_items?, next_meeting_date?}` |
| `PUT minutes.php` | `draft_minutes` | `{id, content}` — only while status is `Draft` |
| `GET minutes_comments.php?minutes_id=N` | | per-agenda-item minute notes |
| `POST minutes_comments.php` | `draft_minutes` | `{minutes_id (or meeting_id), agenda_item_id, comment}` |
| `PUT minutes_comments.php` | `draft_minutes` | `{id, comment}` |

Minutes `status`: `Draft`, `Review`, `Approved`, `Published`. Changing status,
approving and deleting need `manage_minutes` (Clerk/Admin).

## Resolutions

| Call | Perm | Notes |
|---|---|---|
| `GET resolutions.php?meeting_id=M` / `?id=N` | | |
| `GET resolutions.php?register=1[&meeting_type_id=N][&status=S][&category=C][&from=YYYY-MM-DD][&to=YYYY-MM-DD][&q=text]` | | every resolution across meetings |
| `GET resolutions.php?categories=1` | | categories already in use |
| `POST resolutions.php` `{"action": "set_category", "ids": [..], "category": "..."}` | `categorise_resolutions` | bulk set; empty category clears it |

Resolution `status`: `Proposed`, `Consensus`, `Agreement`, `Failed`, `Withdrawn`,
`Lapsed`. Register filter `status=adopted` means Consensus or Agreement.
Creating/editing resolutions needs `create_resolution`/`edit_resolution`.

Procedural proposals (`GET procedural_proposals.php?meeting_id=M`) have status
`Proposed`, `Carried`, `Lost`, `Lapsed`, `Withdrawn`; writes need
`manage_procedural_proposals`.

## Action items

| Call | Perm | Notes |
|---|---|---|
| `GET action_items.php` | | filters: `meeting_id`, `meeting_type_id`, `status`, `assigned_member_id`, `overdue=1`, `mine=1` |
| `GET action_items.php?id=N` | | |
| `POST action_items.php` | `manage_action_items` | `{meeting_id, description, assigned_member_id? or assigned_to_name?, due_date?, status?, notes?, agenda_item_id?, resolution_id?}` or `{meeting_id, items: [...]}` |
| `PUT action_items.php` | `manage_action_items` (any item) or `update_own_action_items` (own items, `status`/`notes` only) | `{id, ...fields}` |
| `DELETE action_items.php` | `delete_action_items` | Clerk/Admin only |

`status`: `Open`, `In Progress`, `Done`, `Cancelled`. `agenda_item_id` /
`resolution_id` must belong to the same meeting.

## Agenda submissions

| Call | Perm | Notes |
|---|---|---|
| `GET agenda_submissions.php?open_meetings=1` | | `Scheduled` meetings in the future that take submissions |
| `GET agenda_submissions.php[?meeting_id=M][&status=S][&mine=1]` | | non-reviewers only see their own |
| `POST agenda_submissions.php` | `submit_agenda_item` | `{meeting_id, title, summary?, proposal?, item_type, decision_method, report_type, duration_minutes?, sharepoint_url}` |
| `PUT agenda_submissions.php` `{id, action: "update" \| "withdraw", ...}` | `submit_agenda_item` | own pending submission |
| `PUT agenda_submissions.php` `{id, action: "accept" \| "decline", version, review_notes?}` | `manage_agenda` | clerks only |

Submission `status`: `Pending`, `Accepted`, `Declined`, `Withdrawn`. A
`Written` report needs a `sharepoint_url` (HTTPS, on the organisation's
SharePoint tenant).

## Documents

| Call | Notes |
|---|---|
| `GET documents.php?meeting_id=M` / `?agenda_item_id=N` / `?id=N` | SharePoint links, not files |

Open documents by giving the user `<base URL>/api/download.php?id=N`, which
re-checks the link. Adding documents needs `upload_documents` (Clerk/Admin).
