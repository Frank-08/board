# Project notes: Standing Committee minutes macros

## Context

Two recurring governance meetings, both minuted by the same secretary (agendas + minutes for each):

- **PiC** — quarterly, the higher-stakes meeting. Already uses `board` end-to-end, including live entry into board's structured minutes UI during the meeting itself.
- **Standing Committee** — monthly. More free-flowing in tone than PiC, but still makes formal decisions and needs numbered agenda items. Currently minuted in plain Word, not board.

Minutes get taken live, during the meeting — not written up afterward from memory or notes.

## Why Standing Committee isn't just "use board"

Two separate reasons, not one:

1. **Live entry is too slow.** board's minutes UI is structured (per-item comments, resolutions via web forms) — good for the permanent record, bad for typing continuously while people are talking. Word wins for live capture: one document, no page loads, no field-by-field saving.
2. **The meeting itself is less formal.** Standing Committee doesn't want PiC's full agenda-item ceremony — but it still needs the numbered agenda items and resolution tracking, just not the structured live-entry workflow that goes with them in board's UI.

## The chosen design

Not "replace Word with board" and not "replace board with more Word tooling" — split by what each is actually good at:

- **Agenda structure & numbering** stays in board. Standing Committee meetings get created in board like any other meeting type, so item numbering and templates come from board, same as PiC.
- **Live minutes & resolutions** get typed in Word during the meeting — free-form under each agenda heading, with a one-keystroke "Resolution" style (Ctrl+Alt+R) for anything formally decided.
- **Sync back to board** happens after the meeting, not necessarily immediately — board is public at togetherincouncil.com, so there's no venue/network constraint on timing. A macro reads the Word doc and pushes minutes text and resolutions back into board via its API, matched to the right agenda item.

Implemented as three VBA macros — see `README.md` for setup, usage, and the specific API field-name assumptions that still need verifying against board's real endpoints.

## Auth for the macros

Session-cookie + CSRF login (what board's browser UI uses) was ruled out in favor of adding proper API key support to board:

- New `api_keys` table (hashed key, not plaintext)
- Each `api/*.php` endpoint checks for an `X-API-Key` header first, falls back to existing session auth so the browser UI is untouched
- A one-off key-generation script is enough for a single-secretary setup — no admin UI needed yet

**Built.** `api_keys` table (`database/migration_add_api_keys.sql`, folded into `schema.sql` for fresh installs), `config/auth.php`'s `isLoggedIn()`/`getCurrentUser()`/`getCurrentRole()` now check `X-API-Key` centrally so every `api/*.php` endpoint gets it for free without per-file changes, and `database/generate_api_key.php` is the one-off key-generation script. A key authenticates as the user it was generated for, so it carries their normal role/permissions — no separate permission model.

## Open items

- [x] Add API key auth to board (table, header check, key generation)
- [ ] Confirm/create a Standing Committee meeting type in board if one doesn't already exist
- [x] Verify the actual JSON field names `GET agenda.php` returns against `modConfig.bas`'s assumptions (`id`, `item_number`, `title`) — matched, no change needed
- [x] Verify the field names `resolutions.php` and `minutes_comments.php` expect on POST — both needed fixes, see `README.md`'s "Field names, checked against the real API"
- [ ] Run `database/migration_add_api_keys.sql` against the live board database, then `php database/generate_api_key.php <username> "Word minutes macro"` for the secretary's account
- [ ] First real test: run `StartMinutes` against a live Standing Committee agenda, take a full meeting's minutes in Word, run `SyncToBoard`, check the result in board
