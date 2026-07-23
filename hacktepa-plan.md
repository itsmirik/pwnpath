---
slug: hacktepa
project: HackTepa
domain: hacktepa.com (verify availability)
bucket: product
status: planning
created: 2026-07-23
target_launch: 2026-10-23 (12 weeks)
build_mode: solo, Claude Code assisted
maintainer: Mirik
---

# HackTepa — Master Plan

CTF / cybersec training platform for Central Asia. leetcode-style: free tier, per-challenge scoring, profile + streak +
leaderboard. Localized RU/UZ/EN, dynamic per-user flags, admin dashboard for content authoring.

Parent idea: `ctf-platform-central-asia.md`.

---

## 1. Vision

**One-line pitch**: "leetcode for cybersecurity in Central Asia — Russian/Uzbek-first, AI-era vulns, free."

**5-year vision**: default place CA cybersec learners spend time. Hiring signal for local banks/fintech.

**3-month vision (MVP)**: 20 challenges live, RU/UZ/EN UI, 500 signups from Telegram push, 100 solved ≥1 challenge,
working scoring + leaderboard.

## 2. Non-goals (MVP)

- ❌ Payments / premium tier — free at launch, monetize v2.
- ❌ Cloud labs (per-user VMs / containers) — v2.
- ❌ Team competitions / private leagues — v2.
- ❌ Mobile app — responsive web only.
- ❌ Reverse engineering + binary pwn challenges — v2 (needs sandbox exec).
- ❌ Corporate / uni tiers — v2.
- ❌ Certifications — v3.
- ❌ Real-time notifications / websockets — poll-based UI.

## 3. Success metrics (12 weeks post-launch)

| Metric                  | Target  | Kill floor |
|-------------------------|---------|------------|
| Signups                 | 500     | <150       |
| Weekly active users     | 100     | <30        |
| Users solved ≥1         | 200     | <50        |
| Users solved ≥5         | 50      | <10        |
| Total challenges live   | 20 → 30 | <15        |
| User writeups submitted | 30      | <5         |
| Uptime                  | 99%     | <95%       |

Kill floor breach → pivot or archive per `ctf-platform-central-asia.md` kill criteria.

## 4. Personas

**P1 — Aziz, 20, CS student TUIT**

- Learns cybersec via YouTube, no structured path
- English: OK for docs, prefers RU
- Budget: $0
- Needs: guided progression, hints, community writeups
- Success = solved 10 challenges + landed junior SOC internship at Kapitalbank

**P2 — Dilnoza, 24, junior backend dev**

- Wants to transition into AppSec at Uzum
- 3 yr PHP/Node experience
- Time: 5-8h/week evenings
- Needs: web + AI/LLM challenges w/ realistic modern webapp scenarios
- Success = solved 15 challenges, portfolio for AppSec interview

**P3 — Timur, 17, high school**

- Aspiring hacker, no coding background
- UZ primary language, minimal RU/EN
- Needs: absolute-beginner tier, UZ UI, OSINT + crypto entry
- Success = learns basics, gets excited, sticks around

## 5. MVP feature scope (MoSCoW)

### MUST

**Auth**

- Email + password signup
- Email verification (magic link)
- Password reset flow
- Session cookies (Laravel default)

**Challenges**

- Browse: list w/ filter by category, difficulty, solved/unsolved
- Detail view: title, description (markdown), difficulty, points, category tags, downloadable files, submit flag input
- Dynamic per-user flag: `flag = "HTP{" + HMAC_SHA256(user_id + challenge_id, SECRET)[:24] + "}"`
- Server-side flag validation
- Solve timestamp recorded

**User profile**

- Public URL: `/u/{username}`
- Displays: avatar (initials-based), XP total, rank, solved count per category, streak (consecutive days w/ ≥1 solve),
  badge grid, solve timeline
- Editable: display name, bio (300 chars), avatar color, country flag

**Scoring**

- Easy = 100 pts, Medium = 300 pts, Hard = 700 pts
- XP = sum of solved challenge points
- Rank tiers: Bronze (0-499), Silver (500-1999), Gold (2000-4999), Platinum (5000-9999), Diamond (10000+)

**Streaks**

- Day counter: consecutive days w/ ≥1 solve (Asia/Tashkent tz for MVP)
- Freeze token: 1 per 30 days, auto-consumed if user misses a day

**Badges** (v1 set — 10)

- First Blood: first solve
- Getting Started: 5 solves
- Warmed Up: 10 solves
- Half Century: 50 solves
- Web Warrior: 5 web solves
- Crypto Cracker: 5 crypto solves
- AI Whisperer: 3 LLM solves
- Streak 7: 7-day streak
- Streak 30: 30-day streak
- Speedrun: solve challenge < 5min of open

**Leaderboards**

- Global all-time (top 100)
- Weekly (rolling 7-day XP earned)
- Per-category top 20

**Writeups**

- Submit after solving only
- Markdown editor, image upload (max 2MB, jpg/png/webp)
- Moderator approves before public
- 1 writeup per user per challenge
- Vote (up only) by other solvers

**Comments**

- Per challenge discussion thread
- Solved-only can post (prevent spoiler farming pre-solve)
- Nested 1-level
- Report button → mod queue
- Rate limit: 5 comments per user per hour

**Anti-abuse**

- Rate limits: 30 flag submissions per user per challenge per hour → 30min cooldown
- 100 flag submissions across all challenges per user per hour → 1h cooldown
- Signup: 5 per IP per day
- CAPTCHA (hCaptcha) on signup + password reset

**i18n**

- RU / UZ / EN
- Language switcher in header, persisted per-user
- All UI strings via translation keys
- Challenge title + description + hints translatable per-locale (fallback: RU → EN → UZ if missing)

**Admin dashboard** — see §8

**Landing page**

- Hero: pitch + CTA
- Feature strip
- 3 sample challenges preview
- Leaderboard peek
- Language switcher
- Signup CTA

**Legal**

- ToS (drafted w/ lawyer or reused template, disclaimer of educational use)
- Privacy policy (data collected: email, IP, activity logs)
- Cookie banner (only if EU visitors → GDPR)
- Age gate: 13+ signup

**Analytics**

- Plausible.io (privacy-friendly, cheap)
- Custom event tracking: signup, solve, writeup submit, comment post

### SHOULD

- OG image auto-gen per challenge (share to Telegram = free marketing)
- Solved emoji reaction stream on landing (social proof)
- Referral link (post-MVP monetization prep)

### COULD

- Dark mode (default dark, cybersec vibe)
- Keyboard shortcuts (leetcode-style: `j/k` navigate)
- Discord webhook for first-blood alerts

### WON'T (MVP)

- Native mobile
- Real-time notifications
- Team features
- Paid tier
- Cloud labs
- User-authored challenges (community submit)

## 6. Data model

Tables (Laravel migrations). Names: snake_case, pluralized.

```
users
  id (bigint, PK)
  username (varchar 32, unique)
  email (varchar 255, unique)
  email_verified_at (timestamp, null)
  password (varchar 255, bcrypt)
  display_name (varchar 64)
  bio (text, null, max 300)
  avatar_color (varchar 7) — hex
  country_code (varchar 2, null) — ISO
  locale (enum: ru/uz/en, default ru)
  role (enum: user/moderator/author/admin, default user)
  streak_count (int, default 0)
  streak_freeze_available (int, default 1)
  last_solve_date (date, null)
  xp_total (int, default 0)
  created_at, updated_at

challenges
  id (bigint, PK)
  slug (varchar 64, unique)
  category (enum: web, crypto, forensics, osint, llm, misc)
  difficulty (enum: easy, medium, hard)
  points (int) — 100/300/700
  author_id (fk users.id)
  status (enum: draft, review, published, archived)
  static_flag (varchar 128, null) — if not dynamic
  flag_type (enum: dynamic, static)
  flag_format (varchar 32) — regex pattern for validation, e.g. "HTP\{[a-f0-9]{24}\}"
  solve_count (int, default 0) — denormalized
  created_at, updated_at, published_at (null)

challenge_translations
  id (bigint, PK)
  challenge_id (fk)
  locale (enum: ru/uz/en)
  title (varchar 128)
  description (text, markdown)
  hint_1 (text, null)
  hint_2 (text, null)
  UNIQUE (challenge_id, locale)

challenge_files
  id (bigint, PK)
  challenge_id (fk)
  filename (varchar 128)
  storage_path (varchar 255)
  size_bytes (int)
  mime_type (varchar 64)
  created_at

solves
  id (bigint, PK)
  user_id (fk)
  challenge_id (fk)
  points_awarded (int)
  time_to_solve_seconds (int, null) — from first view
  ip_address (varchar 45)
  created_at
  UNIQUE (user_id, challenge_id)

flag_submissions
  id (bigint, PK)
  user_id (fk)
  challenge_id (fk)
  submitted_flag (varchar 256)
  is_correct (bool)
  ip_address (varchar 45)
  user_agent (varchar 255)
  created_at
  INDEX (user_id, challenge_id, created_at) — rate limit lookup

challenge_views
  id (bigint, PK)
  user_id (fk)
  challenge_id (fk)
  first_viewed_at (timestamp)
  UNIQUE (user_id, challenge_id)

writeups
  id (bigint, PK)
  user_id (fk)
  challenge_id (fk)
  locale (enum)
  content (longtext, markdown)
  status (enum: pending, approved, rejected)
  moderator_id (fk users.id, null)
  moderation_note (text, null)
  upvote_count (int, default 0) — denormalized
  created_at, updated_at, moderated_at (null)
  UNIQUE (user_id, challenge_id)

writeup_votes
  id (bigint, PK)
  writeup_id (fk)
  user_id (fk)
  created_at
  UNIQUE (writeup_id, user_id)

comments
  id (bigint, PK)
  challenge_id (fk)
  user_id (fk)
  parent_id (fk comments.id, null) — 1-level nesting
  content (text)
  is_hidden (bool, default false) — mod action
  created_at, updated_at

comment_reports
  id (bigint, PK)
  comment_id (fk)
  reporter_id (fk users.id)
  reason (varchar 128)
  status (enum: open, resolved)
  created_at

badges
  id (bigint, PK)
  slug (varchar 32, unique)
  name_ru, name_uz, name_en (varchar 64)
  description_ru, description_uz, description_en (text)
  icon (varchar 64) — asset filename
  criteria_json (json) — programmatic rule

user_badges
  id (bigint, PK)
  user_id (fk)
  badge_id (fk)
  awarded_at (timestamp)
  UNIQUE (user_id, badge_id)

audit_logs
  id (bigint, PK)
  actor_id (fk users.id, null)
  action (varchar 64) — e.g. challenge.publish, writeup.approve, user.role_change
  entity_type (varchar 64)
  entity_id (bigint)
  meta_json (json)
  ip_address (varchar 45)
  created_at

password_resets (Laravel default)
sessions (Laravel default)
```

## 7. API surface

RESTful, JSON. Laravel Sanctum for API auth (SPA session-based).

```
Public:
GET  /api/challenges              — list published, filter+sort
GET  /api/challenges/{slug}       — detail (require auth to see hints)
GET  /api/leaderboard/global
GET  /api/leaderboard/weekly
GET  /api/leaderboard/category/{cat}
GET  /api/u/{username}            — public profile
GET  /api/writeups/{challenge_slug} — approved only

Auth (session):
POST /api/auth/register
POST /api/auth/login
POST /api/auth/logout
POST /api/auth/verify-email/{token}
POST /api/auth/forgot-password
POST /api/auth/reset-password/{token}

User (auth required):
GET  /api/me
PATCH /api/me                     — profile edit
POST /api/challenges/{slug}/view  — mark first-viewed
POST /api/challenges/{slug}/submit — flag submission
POST /api/challenges/{slug}/writeups
GET  /api/challenges/{slug}/comments
POST /api/challenges/{slug}/comments
POST /api/comments/{id}/report
POST /api/writeups/{id}/upvote
DELETE /api/writeups/{id}/upvote

Admin (role: author|moderator|admin):
GET/POST/PATCH/DELETE /api/admin/challenges
POST /api/admin/challenges/{id}/publish   (admin only)
GET  /api/admin/writeups?status=pending
POST /api/admin/writeups/{id}/approve
POST /api/admin/writeups/{id}/reject
GET  /api/admin/comment-reports
POST /api/admin/comments/{id}/hide
GET/PATCH /api/admin/users                (admin only)
POST /api/admin/users/{id}/role           (admin only)
GET  /api/admin/audit-logs                (admin only)
```

## 8. Admin dashboard spec

Separate route: `/admin`. Vue 3 SPA sharing components w/ main app. Access-gated by role.

**Roles + capabilities:**

| Capability                         | User | Author | Moderator | Admin |
|------------------------------------|------|--------|-----------|-------|
| Create/edit own challenges (draft) |      | ✓      |           | ✓     |
| Submit challenge for review        |      | ✓      |           | ✓     |
| Review challenges                  |      |        |           | ✓     |
| Publish/unpublish challenges       |      |        |           | ✓     |
| Approve/reject writeups            |      |        | ✓         | ✓     |
| Hide comments                      |      |        | ✓         | ✓     |
| Resolve reports                    |      |        | ✓         | ✓     |
| Manage users + roles               |      |        |           | ✓     |
| View audit logs                    |      |        |           | ✓     |

**Screens:**

1. **Dashboard home** — stats: pending writeups, open reports, draft challenges, this-week signups/solves.
2. **Challenges list** — table w/ status filter, search. Actions: Edit, View public page, Publish, Archive.
3. **Challenge editor** — see §9.
4. **Writeups moderation** — queue view, side-by-side original challenge context. Approve/Reject w/ note.
5. **Comment reports** — queue. Show comment context (challenge, thread). Hide/Dismiss.
6. **Users** (admin) — table, search by username/email. Actions: change role, reset password (send link), soft-ban.
7. **Audit log** (admin) — filterable by actor, action, date range. Read-only.

## 9. Challenge authoring pipeline

**Format** — challenges as filesystem-first (git-tracked) + DB-cached, OR pure DB. Choose:

**Recommendation: hybrid.** Challenge source-of-truth = folder in `challenges/` git repo (private submodule of main
app). Each challenge = folder:

```
challenges/
  001-web-idor-payme/
    challenge.yml       # metadata: category, difficulty, points, flag_type, flag_format
    ru.md               # RU description
    uz.md
    en.md
    hints/
      ru-1.md
      ru-2.md
      ...
    files/
      dump.pcap
      hint.txt
    solver/
      solution.md       # private, for author reference
    flag.template       # if dynamic: "HTP{{{ HMAC(user_id, challenge_id) }}}", if static: "HTP{static_value}"
```

**Sync flow**:

- Admin dashboard has "Sync from repo" button
- Pulls latest, validates schema, upserts DB rows
- Files uploaded to S3-compatible storage (Hetzner Object Storage / R2)
- Admin then clicks Publish on individual challenges

**Advantage**: version control, PR-based review by external CTF community volunteers, no giant markdown editor in admin
UI.

**Editor UI** (for quick edits without git):

- Markdown editor per locale (SimpleMDE or Milkdown)
- File upload (drag-drop, S3 pre-signed URL)
- Metadata form (category, difficulty, points auto-derived, flag config)
- Preview mode
- Draft → Review → Published state machine

**Challenge quality checklist** (author self-check before submit):

- [ ] All 3 locales filled (RU mandatory, UZ + EN can be auto-translated via DeepL API)
- [ ] Flag format regex tested
- [ ] Hints ordered (soft → strong)
- [ ] Files under 10MB each, total under 25MB
- [ ] Solver doc written (so mods can verify)
- [ ] Difficulty tag matches actual solve time estimate

## 10. Dynamic flag system

**Server-side generation:**

```php
function generateUserFlag(User $user, Challenge $challenge): string {
    $payload = $user->id . ':' . $challenge->id;
    $hash = hash_hmac('sha256', $payload, config('app.flag_secret'));
    $token = substr($hash, 0, 24);
    return "HTP{{$token}}";
}
```

**Storage**: never stored in DB. Recomputed on submit + on challenge open (to display to user? NO — flag is what user
finds by solving. What we DO display is per-user challenge instance: e.g. a personalized URL, session, or embedded token
in challenge files that leads to that user's flag).

**How dynamic flags work for web challenges:**

- Challenge file/URL embeds user's token: `?u={hashed_user_id}` in downloaded file, or session-scoped state on hosted
  mini-app
- When user solves, they get flag containing HMAC of THEIR user_id — sharing it is useless (server rejects other users'
  flag from being submitted by them)

**For crypto/forensics (static files):**

- Two options:
    - (a) Serve per-user file variants (server generates zip on-the-fly with user's flag baked in)
    - (b) Use static flags for these categories (accept sharing risk, offset by rate limits + report suspicious solves)
- Recommendation: (a) for high-value challenges, (b) for tutorial / low-difficulty

**Submit flow:**

```php
public function submit(Request $req, Challenge $ch) {
    $user = $req->user();
    $submitted = trim($req->input('flag'));

    // Rate limit check
    $recentAttempts = FlagSubmission::where('user_id', $user->id)
        ->where('challenge_id', $ch->id)
        ->where('created_at', '>', now()->subHour())
        ->count();
    if ($recentAttempts >= 30) {
        return response()->json(['error' => 'rate_limited'], 429);
    }

    $expected = generateUserFlag($user, $ch);
    $isCorrect = hash_equals($expected, $submitted);

    FlagSubmission::create([...]);

    if ($isCorrect && !Solve::where('user_id', $user->id)->where('challenge_id', $ch->id)->exists()) {
        DB::transaction(function() use ($user, $ch) {
            Solve::create([...]);
            $user->increment('xp_total', $ch->points);
            $ch->increment('solve_count');
            $this->updateStreak($user);
            $this->checkBadges($user);
        });
        return response()->json(['status' => 'solved', 'points' => $ch->points]);
    }

    return response()->json(['status' => $isCorrect ? 'already_solved' : 'wrong']);
}
```

## 11. Anti-abuse

**Layers:**

- Signup: hCaptcha + email verification required to submit flags
- Per-user rate limits (see §5 MUST)
- IP-based signup cap: 5/day
- Suspicious solve detection: if user solves >5 hard challenges in <5min → flag account for review
- Flag-sharing detection: if same wrong-flag string submitted by >3 users → auto-invalidate that flag as "leaked", mark
  it in DB; if it belongs to another user, alert admin

**Ban tooling:**

- Soft-ban: `users.status = 'suspended'` → login blocked, message shown
- Data preserved (solves, writeups) — no cascade delete

## 12. i18n approach

**Frontend (Vue):** `vue-i18n` v9. JSON per locale in `resources/lang/ru.json`, etc.

**Backend (Laravel):** built-in `lang/` folders. Used for email templates + validation messages.

**Content (challenges, badges):** DB tables `challenge_translations`, `badge_translations`.

**Missing translation fallback:** RU → EN → UZ → literal key.

**Language switcher:** header dropdown. Sets cookie `locale` + updates `users.locale` if authed.

**RTL:** none of RU/UZ/EN. Skip.

**UZ challenge translation strategy:**

- Author writes RU (native)
- DeepL API → EN draft (review + fix)
- UZ: human translator (recruit from TUIT student pool, pay $2/challenge or trade for premium later)

## 13. Auth flow

Email + password, Laravel Breeze scaffold.

- Signup → hCaptcha check → create user (unverified) → send verification email → user clicks link → verified → can
  submit flags
- Login → session cookie (httpOnly, secure, sameSite=lax) → CSRF token for POST/PATCH
- Password reset → email link → 1h expiry
- Session timeout: 30 days remember-me, 2h idle otherwise

**Password rules:** min 10 chars, no complexity requirements (per NIST 800-63B). Zxcvbn strength meter frontend.

**No OAuth in MVP.** Add Google + GitHub in v1.1 (2 weeks post-launch, high ROI).

## 14. Tech stack + infra

**Backend:** PHP 8.5 + Laravel 13.
**Frontend:** Vue 3 + Pinia + Inertia.js (SPA feel, monolith DX) + Tailwind CSS.
**DB:** PostgreSQL 16.
**Cache/queue:** Redis 7.
**Storage:** Hetzner Object Storage (S3-compatible) for challenge files + writeup images.
**Email:** Resend (100 free/day → $20/mo for 50k). Alternative: Postmark.
**CAPTCHA:** hCaptcha (free tier).
**Analytics:** Plausible.io (self-hosted or $9/mo cloud).
**Error tracking:** Sentry (free tier: 5k events/mo).
**Uptime:** UptimeRobot free.

**Hosting:** Hetzner Cloud CX22 (2 vCPU, 4GB RAM, €4.90/mo) — enough for MVP.

- Nginx + PHP-FPM
- PostgreSQL on same box
- Redis on same box
- Deploy via GitHub Actions → SSH + zero-downtime rolling script (or use Laravel Forge $19/mo for simplicity)

**Domain:** `hacktepa.com` (Cloudflare Registrar ~$10/yr). Also grab `.uz` variant if <$50/yr.
**DNS + CDN:** Cloudflare free.
**SSL:** Let's Encrypt via Certbot.

**Repos:**

- `hacktepa-app` (main Laravel app) — public? no, private
- `hacktepa-challenges` (challenge content) — private
- Both on GitHub

**Local dev:** Laravel Sail (Docker Compose w/ Postgres + Redis).

## 15. Legal / compliance

**Entity:** register IE ("Yakka tartibdagi tadbirkor") in UZ once revenue starts. MVP: personal ownership OK, no legal
entity needed pre-revenue.

**ToS musts:**

- Educational use only
- No warranty
- Prohibited: attacking real systems, using challenge techniques on non-consenting targets
- Content moderation right (challenges, writeups, comments)
- Account termination clause
- Age 13+

**Privacy:**

- Data collected: email, hashed password, IP (auth + submissions), User-Agent, locale, activity
- Retention: activity logs 12mo, then aggregated + purged
- Deletion: user can request account delete → soft-delete, purge after 30 days
- GDPR: applies to EU visitors — comply from day 1 (Plausible = GDPR-friendly, no cookies needed)
- UZ Personal Data Law (547-IV): EU-hosted MVP defensible under "no local users' data at scale" but risky for uni deals.
  Migrate to hybrid before uni contracts.

**Cookie banner:** only for EU (Cloudflare geo-detect). Local: no cookies except session.

**License:** challenges CC-BY-SA 4.0 for community writeups; platform code proprietary.

## 16. Content plan — 20 launch challenges

Distribution (matches user's category picks):

| Category                                        | Count | Difficulty split |
|-------------------------------------------------|-------|------------------|
| Web (XSS/SQLi/IDOR/SSRF)                        | 6     | 3E / 2M / 1H     |
| AI/LLM (prompt injection, jailbreak, data leak) | 4     | 2E / 1M / 1H     |
| Crypto                                          | 4     | 2E / 1M / 1H     |
| Forensics                                       | 3     | 1E / 1M / 1H     |
| OSINT                                           | 2     | 1E / 1M          |
| Misc/Stego                                      | 1     | 1E               |

**Draft titles (RU/EN pair, UZ pending translation):**

Web:

1. E — "Cookie Monster" — session cookie theft via reflected XSS
2. E — "Клиент прав" / "The Client is Right" — IDOR in mock e-comm order lookup
3. E — "Admin Panel Payme-стайл" — auth bypass via header injection (Payme-inspired)
4. M — "Blind Truth" — blind SQLi in fake support ticket search
5. M — "Внутренний мир" / "Internal World" — SSRF to metadata endpoint on mock cloud instance
6. H — "OAuth или нет?" — OAuth redirect_uri bypass chain

AI/LLM:

7. E — "Скажи волшебное слово" / "Say the Magic Word" — basic prompt injection to reveal system prompt
8. E — "Роль поменяли" / "Role Reversal" — jailbreak via role-play
9. M — "Утечка контекста" / "Context Leak" — RAG poisoning via user-uploaded doc
10. H — "Function Calling Abuse" — LLM tool-use exploit to call unauthorized function

Crypto:

11. E — "Caesar's Ghost" — classical cipher w/ twist
12. E — "База64 навсегда" / "Base64 Forever" — nested encoding puzzle
13. M — "Weak Random" — predictable RNG in fake auth token
14. H — "RSA Broken" — small-e attack on shared modulus

Forensics:

15. E — "Deleted But Not Gone" — file carving from disk image
16. M — "Traffic Analysis" — extract credentials from PCAP
17. H — "Memory Games" — Volatility on memory dump to find flag in process

OSINT:

18. E — "Кто это?" / "Who is This?" — reverse image search + social media pivot
19. M — "Public Records" — corp registry + cert transparency to find hidden subdomain

Misc:

20. E — "Матрёшка" / "Matryoshka" — nested archive w/ stego at bottom

**Content production timeline** (parallel to build):

- Weeks 1-4: draft all 20 in RU
- Weeks 4-8: EN via DeepL + human polish
- Weeks 8-11: UZ via hired translator
- Week 12: final QA solve-through by 2 volunteers

## 17. Timeline — 12-week plan

Budget: 15-20h/week solo, evenings + weekends. Health note: 45-min pomodoros mandatory, 15-min break, no back-to-back >
4h sessions. See `health/conditions.md`.

Weeks are Mon-Sun.

**Week 1 (Jul 27 – Aug 2): Foundation**

- Buy `hacktepa.com`, provision Hetzner box, DNS via Cloudflare
- Laravel 11 skeleton + Inertia + Vue 3 + Tailwind
- PostgreSQL + Redis wired
- CI: GitHub Actions running Pest + phpstan
- Base layout, dark theme, i18n scaffold (RU/UZ/EN JSON stubs)
- Deploy pipeline working (auto-deploy on main push)

**Week 2 (Aug 3–9): Auth + user model**

- Users table, migrations
- Signup, login, email verification (Resend integration)
- Password reset
- hCaptcha wired
- Profile page (view + edit)
- Locale switcher

**Week 3 (Aug 10–16): Challenge model + browse**

- Challenges + translations + files tables
- Public challenge list + filters
- Challenge detail page (auth-gated hints)
- File download w/ signed URLs
- Seed 3 dummy challenges for dev

**Week 4 (Aug 17–23): Flag submission + solve tracking**

- Dynamic flag HMAC system
- Submit endpoint + rate limits
- Solves table + XP update
- Streak logic + freeze token
- Notify user on solve (in-app toast)

**Week 5 (Aug 24–30): Scoring + leaderboards**

- XP + rank tier computation
- Global leaderboard
- Weekly leaderboard (Redis sorted set, rolling)
- Per-category leaderboards
- Public profile w/ solve timeline

**Week 6 (Aug 31 – Sep 6): Badges**

- Badges + user_badges tables + seeder for 10 v1 badges
- Badge criteria engine (event-driven: on solve, check applicable badges)
- Badge display on profile
- Award notification

**Week 7 (Sep 7–13): Writeups**

- Writeups table + endpoints
- Markdown editor (Milkdown) integrated
- Image upload to Hetzner S3
- Public writeups list per challenge
- Upvote logic

**Week 8 (Sep 14–20): Comments + reports**

- Comments (1-level nesting)
- Solved-gate on posting
- Rate limits
- Report + report queue

**Week 9 (Sep 21–27): Admin dashboard part 1**

- Admin routes + role middleware
- Dashboard home w/ stats
- Challenge editor (draft/review/publish state machine)
- Repo sync ("Import from git" button)

**Week 10 (Sep 28 – Oct 4): Admin dashboard part 2**

- Writeup moderation queue
- Comment reports queue
- Users management (admin only)
- Audit log
- Anti-abuse checks (suspicious solve pattern)

**Week 11 (Oct 5–11): Content + polish**

- Load 20 real challenges via repo sync
- Solve-through each yourself (verify)
- Landing page final copy
- OG image gen
- Legal pages (ToS + Privacy) live
- Sentry + Plausible + UptimeRobot wired

**Week 12 (Oct 12–18): Beta + launch**

- Private beta: invite 10 people from DEFCON Tashkent Telegram
- Fix reported bugs
- Recruit 2 UZ translators, get UZ content ready
- **Oct 19-23**: soft launch, post in RU/UZ CTF Telegrams

**Week 13+ (Oct 24+): Post-launch**

- Monitor + fix
- Add Google/GitHub OAuth (v1.1)
- Ship weekly new challenge

## 18. Cost estimate

**One-time:**

- Domain: $10-30
- hCaptcha: $0 (free tier)
- Design/logo: $0-200 (DIY w/ Figma, or Fiverr)
- Legal review of ToS: $0 (template) — $300 (local lawyer, recommended pre-uni deals)

**Monthly recurring:**

- Hetzner CX22: €4.90 ≈ $5
- Hetzner Object Storage 100GB: €4.30 ≈ $5
- Resend: $0 → $20 above 100/day
- Plausible cloud: $9 (or self-host $0)
- Sentry: $0 (free tier)
- Cloudflare: $0
- UptimeRobot: $0
- **Total MVP**: ~$15-30/mo

**Content:**

- UZ translation (20 challenges): $40-100 (student rate)

**Marketing (first 3 months):**

- Telegram promo posts: $50-200 (paid ads in cybersec channels)
- Meetup sponsor (Tashkent, once): $100-300

**Total launch budget**: ~$300-800 out-of-pocket first 6 months.

## 19. Risks + mitigations

| Risk                                          | Likelihood | Impact | Mitigation                                                    |
|-----------------------------------------------|------------|--------|---------------------------------------------------------------|
| Solo burnout / spine issue flare              | High       | High   | Enforced pomodoros, weekly deload day, physio session monthly |
| Content quality < HTB → users bounce          | Med        | High   | 2 volunteer solvers verify each challenge before publish      |
| No UZ translator found in time                | Med        | Med    | Launch RU+EN, add UZ v1.1 (2 weeks after)                     |
| DeepL EN translations bad                     | Med        | Low    | Manual polish pass on each                                    |
| Flag-sharing on Telegram                      | High       | Med    | Dynamic flags + rate limits + leaked-flag detection           |
| Hosting costs balloon w/ 10k users            | Low        | Med    | CX22 handles ~50k MAU; upgrade tier $10-20                    |
| Legal issue (attack tools)                    | Low        | High   | ToS educational-only clause; no real-target challenges        |
| Competitor launches first                     | Low        | Med    | Ship fast; local moat > global player features                |
| Payme/UZ payment blocking future monetization | Med        | Med    | Test payment rails in weeks 8-10, before soft launch          |
| Domain `hacktepa.com` unavailable             | Low        | Low    | Backups: `hacktepa.io`, `hacktepa.uz`, `hactepa.com`          |

## 20. Post-launch roadmap (v1.1 → v3)

**v1.1 (Nov 2026):**

- OAuth (Google + GitHub)
- 5 new challenges
- Referral link tracking
- Solve-attempt analytics for challenge authors

**v1.2 (Dec 2026):**

- Community challenge submission (author role → open to select users)
- Discord/Telegram integration (first-blood alerts)
- Weekly newsletter

**v2 (Q1 2027):**

- Cloud labs (per-user Docker containers, K8s-orchestrated)
- Pwn + reverse challenges
- Premium tier: $5/mo (unlimited hints, lab hours, exclusive challenges)
- Payme + Click integration

**v2.5 (Q2 2027):**

- Team competitions + private leagues
- Corporate portal (recruit companies pay for hiring CTF)
- Uni pilot program

**v3 (H2 2027):**

- Certifications w/ signed badges
- Mobile app (React Native)
- Multi-region hosting (UZ mirror for compliance)

## 21. Phase prompt outlines

Each phase = one Claude Code session. Feed this doc + phase prompt below. Fresh session per phase to keep context tight.

### Phase 1 prompt (Week 1 — Foundation)

```
Read hacktepa-plan.md. Sections 14, 6, 12. Build:
1. Laravel 13 + Inertia + Vue 3 + Pinia + Tailwind skeleton
2. PostgreSQL + Redis via Sail
3. i18n scaffold: vue-i18n + Laravel lang folders. RU/{module}/{feature}.json/UZ/{module}/{feature}.json/EN/{module}/{feature}.json JSON stubs.
4. Base layout: header (logo, lang switcher, login/signup), footer (legal links).
5. Dark theme default (Tailwind slate-950).
6. Landing page skeleton (hero, features grid, CTA).
7. GitHub Actions CI: composer install, npm ci, phpstan level 6, Pest.
8. .env.example w/ all required vars.
Do NOT: build auth (Phase 2). Do NOT: build challenges (Phase 3).
Deliverable: `sail up` runs, landing page loads at localhost, CI green.
```

### Phase 2 prompt (Week 2 — Auth)

```
Context: Phase 1 done. Read hacktepa-plan.md sections 6 (users table), 13 (auth flow).
Build:
1. Users migration + model per §6 schema.
2. Laravel Breeze (Inertia + Vue) scaffold.
3. Modify to include: display_name, avatar_color, locale, role fields.
4. Email verification via Resend. Env var RESEND_API_KEY.
5. hCaptcha on signup + password reset. Env HCAPTCHA_KEY, HCAPTCHA_SECRET.
6. Profile page (view /u/{username} + edit /profile).
7. Locale switcher persisting to cookie + users.locale.
8. Test w/ Pest: signup/login/verify/logout happy paths.
Do NOT: OAuth (v1.1). Do NOT: password strength meter (nice-to-have, skip).
Deliverable: user can sign up, verify email, login, edit profile, switch language.
```

### Phase 3 prompt (Week 3 — Challenges browse)

```
Context: Phase 1-2 done. Read hacktepa-plan.md sections 6, 7 (challenge routes), 9 (authoring format).
Build:
1. Challenges + challenge_translations + challenge_files migrations.
2. Challenge model + Translatable trait (locale-aware attribute accessors).
3. Public challenge list page /challenges w/ filter (category, difficulty, solved/unsolved) and sort.
4. Challenge detail page /challenges/{slug} — description, hints (auth-gated), file downloads, submit form.
5. Signed S3 URLs for file downloads (Hetzner Object Storage config).
6. Seed 3 dummy challenges w/ RU/EN translations for dev.
Do NOT: implement flag submission logic yet (Phase 4). Submit form can POST to stub endpoint returning 501.
Deliverable: browse + detail pages functional, seed data visible.
```

### Phase 4 prompt (Week 4 — Flags + solves)

```
Context: Phase 1-3 done. Read hacktepa-plan.md sections 6 (solves, flag_submissions), 10 (dynamic flags), 11 (anti-abuse).
Build:
1. Solves + flag_submissions + challenge_views migrations.
2. Dynamic flag generator per §10 (HMAC).
3. Submit endpoint per §10 pseudocode: rate limit → validate → record → award.
4. Streak update logic (users.streak_count, last_solve_date, freeze token consumption).
5. Anti-abuse: signup IP cap, flag-share leak detection.
6. Feature flag: allow static flags for some challenges (flag_type enum).
7. Pest tests: correct flag, wrong flag, rate limit trip, double-solve idempotency, streak day-by-day.
Deliverable: user can submit flag, get scored, streak updates.
```

### Phase 5 prompt (Week 5-6 — Scoring + badges + leaderboards)

```
Context: Phase 1-4 done. Read hacktepa-plan.md sections 5 (badges list, ranks), 6 (badges, user_badges), 7.
Build:
1. XP + rank tier computed props on User model.
2. Badges + user_badges migrations + seeder for 10 v1 badges (§5).
3. Badge criteria engine: event listener on Solve created → checks all badges, awards new ones.
4. Global leaderboard endpoint + page.
5. Weekly leaderboard via Redis ZADD on solve, ZREVRANGEBYSCORE for query, TTL 8 days.
6. Per-category leaderboards.
7. Profile page: solved-per-category counts, badges grid, solve timeline.
Deliverable: leaderboards + badges + profile all working.
```

### Phase 6 prompt (Week 7-8 — Writeups + comments)

```
Context: Phase 1-5 done. Read hacktepa-plan.md section 5 (writeups + comments MUST), 6 (tables), 7.
Build:
1. Writeups + writeup_votes migrations.
2. Writeup submit page (solved-gate). Milkdown markdown editor. Image upload to S3.
3. Public writeups list per challenge — approved only.
4. Upvote (auth users who solved the challenge only).
5. Comments migration. 1-level nesting.
6. Comments UI on challenge detail, solved-gate.
7. Rate limit: 5 comments/user/hour.
8. Report button → comment_reports.
Do NOT: build moderation UI yet (Phase 7).
Deliverable: solvers can submit writeups + comment; content queued pending mod.
```

### Phase 7 prompt (Week 9-10 — Admin dashboard)

```
Context: Phase 1-6 done. Read hacktepa-plan.md section 8 (admin spec), 9 (authoring pipeline).
Build:
1. /admin routes, role middleware (redirect non-admin/mod/author to 403).
2. Admin layout (separate nav, minimal chrome).
3. Dashboard home w/ stats: pending writeups count, open reports, drafts, this-week signups/solves.
4. Challenge editor: metadata form, per-locale markdown, file drag-drop, draft/review/publish state machine.
5. "Sync from repo" button: pulls challenges git repo, validates YAML, upserts DB rows, uploads files to S3.
6. Writeup moderation queue: approve/reject w/ note. Audit log entry.
7. Comment reports queue: hide comment / dismiss report.
8. Users table (admin only): search, change role, soft-ban.
9. Audit log page (admin only): filterable table.
10. Anti-abuse: suspicious solve detection cron.
Deliverable: full admin functional; can create challenge → publish → moderate content.
```

### Phase 8 prompt (Week 11-12 — Launch prep)

```
Context: Phase 1-7 done. Read hacktepa-plan.md sections 15 (legal), 16 (content), 17 (week 11-12).
Build:
1. Legal pages: ToS + Privacy Policy (draft from templates + fill placeholders).
2. Cookie banner (Cloudflare geo-detect for EU).
3. OG image auto-gen per challenge (dynamic PNG endpoint).
4. Landing page final copy — hero, features, sample challenges preview, leaderboard peek.
5. Wire Sentry (SENTRY_DSN env), Plausible (script tag), UptimeRobot config.
6. SEO: sitemap.xml, robots.txt, meta tags per page.
7. Load 20 real challenges via repo sync.
8. Deploy checklist runbook (docs/RUNBOOK.md).
Deliverable: production-ready. Soft launch invite goes out.
```

---

## 22. Open questions / decisions to revisit

- [ ] `.uz` TLD purchase — check pricing, worth it?
- [ ] Uzbek translator hire — where to find (INHA / TUIT student boards)?
- [ ] Discord vs Telegram community — which primary?
- [ ] License for user-submitted writeups (CC-BY-SA vs platform-owned)?
- [ ] Should badges have secret/hidden variants (drives exploration)?
- [ ] Analytics stack: Plausible self-host on same box vs $9 cloud?

## 23. Related

- Parent idea: [ctf-platform-central-asia.md](ctf-platform-central-asia.md)
- Career fit: [preferences.md](../career/preferences.md), [skills.md](../career/skills.md)
- Health caveat: solo build risks — see [conditions.md](../health/conditions.md), enforce pomodoro discipline
- Related research: [hackathon-ideas-uzbekistan.html](research/hackathon-ideas-uzbekistan.html)
