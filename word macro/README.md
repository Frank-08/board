# Standing Committee minutes macros

Three modules that pull a board agenda into Word, let you free-type
minutes and resolutions live, then push the structured result back to
board once you're online.

## Files

- `modConfig.bas` - board URL, API key placeholder, field-name constants
- `modBoardAPI.bas` - HTTP + minimal JSON helpers
- `modMinutesWorkflow.bas` - the three macros you actually run

## One-time setup

1. **Generate an API key.** `X-API-Key` auth is now built into board
   (`config/auth.php`'s `currentApiKeyUser()`) - it's checked on every
   `api/*.php` endpoint, session auth still works unchanged for the
   browser UI. On the server, apply `database/migration_add_api_keys.sql`
   if it hasn't run yet, then generate a key for the secretary's account:
   `php database/generate_api_key.php <username> "Word minutes macro"`.
   The key is only shown once - copy it straight into `API_KEY` below.
2. In Word, `Alt+F11` to open the VBA editor.
3. `File > Import File...` for each of the three `.bas` files. Import
   them into **Normal.dotm** (or a dedicated add-in template you load
   from the STARTUP folder - either works, Normal is simplest to start).
4. In `modConfig.bas`, set `API_KEY` to the real key once you've
   generated one.
5. Run `SetupResolutionStyle` once (`Alt+F8`, pick it, Run). This
   creates the "Resolution" style and binds `Ctrl+Alt+R`, saved into
   Normal.dotm so it's everywhere from then on.
6. Optional: add `StartMinutes` and `SyncToBoard` as Quick Access
   Toolbar buttons (Word Options > Quick Access Toolbar > choose
   commands from "Macros") so you're not hunting through `Alt+F8`
   mid-meeting.

## Using it

- **Before/at the meeting:** run `StartMinutes`, paste in the board
  meeting ID (visible in board's URL when you're viewing that
  meeting). It builds a new doc with one heading per agenda item,
  numbered the way board numbers them.
- **During the meeting:** free-type under each heading as normal.
  When something's resolved, hit `Ctrl+Alt+R` and type the resolution
  text.
- **Whenever you're next online:** run `SyncToBoard` on that document.
  It posts each item's typed content back as a minutes comment, and
  each resolution-styled line as a resolution, matched to the right
  agenda item automatically.

## Field names, checked against the real API

These were verified against `board`'s actual `api/*.php` and
`database/schema.sql`, and the macros adjusted to match:

- `GET agenda.php` returns `id`, `item_number`, `title` per item, as
  assumed - the constants in `modConfig.bas` are correct as-is.
- `POST resolutions.php` requires `title` (VARCHAR 255) and
  `description` (TEXT), not a single `resolution_text` - the table has
  no free-text-only column. `SyncToBoard` now sends the full
  Resolution-styled line as `description`, and a truncated version
  (see `ResolutionTitle` in `modMinutesWorkflow.bas`) as `title`.
- `POST minutes_comments.php` required `minutes_id` (the `minutes`
  table's own id, not the meeting's), which the macro has no way to
  know. Rather than have the macro guess it, the endpoint itself was
  extended to accept `meeting_id` as an alternative - it finds or
  creates that meeting's `minutes` row server-side. `SyncToBoard`
  already sent `meeting_id`, so no macro change was needed here.

## Known limitations

- `JsonField` is a hand-rolled parser scoped to board's flat agenda
  response - it won't handle nested JSON, and can't tell a missing
  field from a genuinely empty string.
- `SyncToBoard` isn't idempotent: if a run partially fails, re-running
  will re-post the items that already succeeded. Fine for a monthly
  meeting where you can eyeball the result in board afterward; would
  need real dedup logic for anything higher-stakes.
- None of this has been run against live Word or a live board
  instance - treat it as a solid first draft, not tested code.
