# Together in Council — Android app

A sideloadable Android client for the "Together in Council" board meeting
system. Native Kotlin/Jetpack Compose screens cover the field workflow
(dashboard, meeting list, and full meeting detail — agenda, attendees,
minutes, resolutions, procedural proposals, departures); rarer admin
screens (members, users, 2FA setup) are reached through Chrome Custom Tabs
pointed at the existing PHP pages.

See `/root/.claude/plans/develop-an-android-app-whimsical-tulip.md` (or ask
whoever built this) for the full design rationale. The short version:

- **Auth**: an `X-API-Key` header, the same mechanism the VBA Word macros
  (`../word macro/`) already use against this backend. Provision a key
  per user with `php database/generate_api_key.php <username> <label>`
  (run from the repo root, on the server) and hand the raw key to that
  user once — it can't be recovered afterwards.
- **Backend addition**: `api/whoami.php` (already added alongside this
  module) lets the app discover the current key's role and full
  permission map. If a server hasn't deployed it yet, onboarding falls
  back to asking the user to manually pick their role.
- **No offline write queue.** This is an online, in-meeting field tool,
  not an offline-first app — see the design doc for why.

## Prerequisites to build

- Android Studio (Ladybug/Koala or newer) or a standalone Android SDK with
  `compileSdk 35` / build-tools installed.
- JDK 17+.

### A note on this repository's sandbox

This module was scaffolded in a sandboxed environment with **no access to
`dl.google.com` / the Android SDK / Google's Maven repository** (outbound
network policy blocks it), so:

- **The Gradle wrapper (`gradlew`, `gradlew.bat`, `gradle/wrapper/gradle-wrapper.jar`) is not included.** Generate it the first time you open this
  project somewhere with normal internet access:
  ```
  cd android
  gradle wrapper --gradle-version 8.9
  ```
  (or just open `android/` in Android Studio — it offers to do this for
  you on first sync). From then on commit the wrapper files as normal.
- **None of this Kotlin/Compose code has been compiled or run** — it was
  written carefully against the actual `api/*.php` contracts and
  cross-checked for balanced syntax, but there was no way to run
  `gradlew assembleDebug` or a Kotlin compiler in this environment to
  catch type errors. **Treat this as a from-scratch implementation that
  needs a first real build-and-fix pass**, not as pre-verified code.
  Expect to spend some time on ordinary first-build issues (a missed
  import, a Compose API signature drift between library versions, etc.)
  before it runs.

## Running against a local dev backend

1. Stand up the PHP backend per the root `CLAUDE.md`'s "Running locally"
   section (Apache/PHP-FPM, `config/database.php`, `schema.sql` +
   migrations).
2. Generate a test API key per role you want to exercise:
   ```
   php database/generate_api_key.php <username> "Android dev"
   ```
3. Point the debug build at your dev server. Either:
   - Edit the `debug` build type's `buildConfigField("String", "API_BASE_URL", ...)` in `app/build.gradle.kts` (commented placeholder already
     there), or
   - Override `BuildConfig.API_BASE_URL` via a build variant / local
     `local.properties`-driven Gradle property if you prefer not to edit
     the build file directly.
   - On the emulator, `10.0.2.2` reaches the host machine's `localhost`;
     a debug-only cleartext exception for `10.0.2.2`/`localhost` is
     already wired up in `app/src/debug/res/xml/network_security_config.xml`
     so HTTP (not HTTPS) works against a local dev server without
     touching the release network security config.
   - On a physical device on the same LAN, use the host's LAN IP instead
     and add it to that same debug network security config file.
4. `./gradlew installDebug` (once the wrapper is generated) or run from
   Android Studio.
5. Paste the generated API key into the onboarding screen.

## Building a sideload release

1. Generate a real signing keystore once (not committed):
   ```
   keytool -genkeypair -v -keystore tic-release.jks -alias tic \
     -keyalg RSA -keysize 2048 -validity 10000
   ```
2. Create `android/keystore.properties` (gitignored) alongside
   `settings.gradle.kts`:
   ```
   storeFile=/absolute/path/to/tic-release.jks
   storePassword=...
   keyAlias=tic
   keyPassword=...
   ```
3. `./gradlew assembleRelease` → `app/build/outputs/apk/release/app-release.apk`.
4. Distribute that file however you already distribute trusted files to
   your users (email, shared drive, or a static file under the existing
   site's HTTPS domain). There's no in-app auto-update in this version —
   re-send the APK when you cut a new one.
5. On the device: open the APK from whatever app you send it through
   (Chrome, Files, Gmail...) and approve that app's one-time "install
   unknown apps" permission when prompted. No separate settings dive is
   needed on modern Android — the OS scopes that permission to the
   specific app the install came from.

Never ship a debug-signed build for real use.

## Manual verification checklist

Once the wrapper is generated and the app builds, walk through (per role —
Viewer/Member/Clerk/Admin, one API key each):

- **Onboarding**: valid key lands on Dashboard with the right
  username/role; invalid key shows a clear inline error and doesn't
  crash; if testing without `api/whoami.php` deployed, confirm the
  manual role-picker fallback appears instead.
- **Dashboard / Meeting list**: meeting-type and status filters narrow
  results correctly.
- **Agenda tab**: reordering (up/down arrows) produces the same
  resulting order and item numbering as reordering the same items via
  the desktop web UI — compare side by side.
- **Attendees tab**: a Member can only change their own row; Clerk/Admin
  can change any row; the meeting header's quorum indicator updates
  after a status change that crosses the quorum threshold.
- **Minutes tab**: a per-item comment saved (tap away to blur) persists
  after reloading the tab; Approve immediately locks the Resolutions tab
  and this tab's edit controls without needing to hit a 409 first.
- **Resolutions tab**: a `_warning` in the response (e.g. include
  "proxy" in clerk notes) shows as a snackbar, not an error; a Formal
  Majority resolution missing a mover/seconder is blocked before
  submitting; attempting to save against an already-approved meeting
  surfaces the server's 409 message cleanly.
- **Documents / exports**: a SharePoint link opens an external
  browser/OneDrive app, not inside this app; a PDF export
  (`export/agenda_pdf.php`) opens directly with no unexpected auth
  prompt.
- **More menu**: each row opens the right PHP page in a Custom Tab; a
  first visit prompts the page's own login; subsequent visits in the
  same run don't re-prompt.
- **401 handling**: revoke the test key directly in the DB
  (`UPDATE api_keys SET revoked_at = NOW() WHERE ...`), trigger any
  API call, and confirm the app cleanly bounces back to Onboarding
  instead of showing a raw error.

## Known v1 limitations (by design, see the plan doc for rationale)

- No offline write queue — writes require connectivity.
- No self-service API key generation in-app; an admin provisions keys
  via the CLI script.
- No in-app auto-update.
- "Meeting Types" and "Agenda Templates" admin aren't wired into the
  More menu yet — confirm those actually have standalone pages (they
  may only exist as inline modals within another page) before adding
  rows for them.
