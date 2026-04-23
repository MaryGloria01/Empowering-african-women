# EAW Project — Claude Code Instructions

## Project Overview
**Empowering African Women (EAW)** — DBTWTEi Nigeria  
Full-stack web platform: Vanilla HTML/CSS/JS frontend + PHP/MySQL backend (Hostinger)  
Live site: https://empoweringafricanwomen.com

## Tech Stack
- **Frontend**: Vanilla HTML, CSS, JavaScript (no frameworks)
- **Backend**: PHP 8+, MySQL via PDO
- **Auth**: PHP sessions + localStorage cache
- **Hosting**: Hostinger Business (LiteSpeed reverse proxy, SSL terminated at proxy)
- **Deployment**: FTP to `public_html/` + Git push to GitHub

## Key Files
- `api/db.php` — DB connection, session helpers, CORS, debug logging
- `api/login.php`, `api/logout.php`, `api/signup.php` — Auth endpoints
- `api/enroll.php`, `api/progress.php`, `api/certificate.php` — Course data
- `eaw-common.js` — Shared JS: session restore, enrollment sync, quiz gate
- `student-dashboard.html` — Student portal
- `styles.css` — Design system (navy/blue/gold palette, Source Serif 4 + Source Sans 3)
- `index.html` — Homepage

## Critical Rules
- **ALL courses are FREE** — never add payment gates or premium locks
- **Server is single source of truth** — never push stale localStorage to DB
- **Never add `Co-Authored-By`** in commits — breaks GitHub streak
- **Always push to BOTH branches**: `git push origin master` AND `git push origin master:main`
- **Push at end of every session** — do not wait to be asked

## PHP / Backend Rules
- Hostinger LiteSpeed terminates SSL — detect HTTPS via `X-Forwarded_Proto` header
- Session cookies: `secure: $isHttps`, `samesite: Lax` (never hardcode `true`/`Strict`)
- All API files must call `start_session()` before any `debug_log()` call
- Debug log format: `[timestamp] [file.php] [uid=X] message`

## Git / GitHub
- Repo: https://github.com/MaryGloria01/Empowering-african-women
- Default branch: `main` (local branch is `master`)
- After every session: `git push origin master && git push origin master:main`
- Never include: `.claude/`, `portal-management-eaw2024.html`, `config.php`, `debug.log`

---

## Skills

The following skills from [alirezarezvani/claude-skills](https://github.com/alirezarezvani/claude-skills) are active.  
Invoke with `/review`, `/backend`, `/frontend`, or `/fullstack`.

### Senior Fullstack Engineer
Act as a senior fullstack engineer when building or reviewing features end-to-end.

**Priorities:**
- Security first: validate all inputs, parameterize all SQL, sanitize outputs
- Server is source of truth — client caches only, never overwrites server
- No premature abstraction — solve the problem at hand, no over-engineering
- Performance: minimize DB queries, avoid N+1, cache aggressively

**Code quality standards:**
- Functions under 50 lines; files under 500 lines
- No hardcoded secrets, credentials, or environment-specific values
- Input validated at system boundaries (user input, external APIs)
- Errors logged server-side; generic messages returned to client

### Senior Backend Engineer (PHP)
Act as a senior backend PHP engineer for all API and server-side work.

**Workflows:**
1. **API design**: RESTful endpoints, proper HTTP verbs and status codes
2. **Security hardening**: CSRF tokens, rate limiting, prepared statements, session security
3. **Performance**: PDO with static singleton, `FETCH_ASSOC`, `INSERT IGNORE` for idempotent ops
4. **Auth flows**: Session-based auth behind reverse proxy — always check `X-Forwarded-Proto`

**PHP patterns to follow:**
- Use `json_out()` helper for all responses — never echo raw JSON
- Use `get_input()` for POST body — never `$_POST` for JSON payloads
- Always call `cors()` then `start_session()` at the top of every API file
- Rate limit sensitive endpoints (login, signup) with file-based counters

### Senior Frontend Engineer (Vanilla JS/CSS)
Act as a senior frontend engineer for HTML/CSS/JS work — no frameworks.

**Priorities:**
- Semantic HTML, accessible markup (ARIA where needed)
- CSS: use existing design system classes — never add inline styles or duplicate rules
- JS: avoid race conditions between `DOMContentLoaded` handlers
- Never call build/destructive DOM functions if element already exists — check first

**Design system (styles.css):**
- Colors: Navy `#0F172A`, Blue `#1D4ED8`, Gold `#D97706`, Teal `#0F766E`
- Fonts: Source Serif 4 (headings), Source Sans 3 (body)
- Buttons: `.btn-blue`, `.btn-gold`, `.btn-white`, `.btn-ghost`, `.btn-teal`

### Code Reviewer
When reviewing code, check for:

**Security (block if found):**
- SQL injection (non-parameterized queries)
- XSS (unescaped output to DOM)
- Hardcoded credentials or secrets
- Command injection
- Missing CSRF protection on state-changing endpoints

**Quality (flag and fix):**
- Silent error swallowing (`.catch(function(){})` with no handling)
- Race conditions between async operations
- Stale cache being pushed to server
- Business logic in wrong layer (DB logic in JS, presentation in PHP)

**Score: Approve (90+) / Needs Work (70-89) / Block (<70 or critical issue)**
