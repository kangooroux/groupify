# Commander Pods Randomizer

Web app that generates random pod groupings for MTG Commander play sessions.
Minimizes player conflicts across rounds.

## Tech Stack

- PHP 8.3 (strict types enforced), Symfony 7.4
- PostgreSQL 16, Doctrine ORM + Migrations
- Twig templates, vanilla JS/CSS (no frameworks)
- Docker + Docker Compose for local dev
- Deployed to Railway.app

## Local Development

```bash
make up       # Start containers (PHP-FPM, Nginx :8000, Postgres, Adminer :8080)
make down     # Stop containers
make bash     # Shell into PHP container
make migrate  # Run pending migrations
make composer cmd="require foo/bar"  # Run composer commands
```

App: http://localhost:8000
DB admin: http://localhost:8080

## Project Structure

- `src/Controller/GroupController.php` — all routes, CSRF, session state
- `src/Service/GroupingService.php` — core grouping algorithm
- `templates/group/` — main and print views
- `public/css/`, `public/js/` — assets (vanilla, no bundler)
- `migrations/` — Doctrine migrations
- `config/` — Symfony config (packages/, routes.yaml, services.yaml)

## Key Conventions

- `declare(strict_types=1)` on every PHP file
- Single controller handles all routes
- Business logic belongs in `GroupingService`, not the controller
- Redirect-after-POST to prevent form resubmission
- Session-based state (stateless DB — entity layer exists but unused)
- Route/DI via PHP attributes (`#[Route]`, `#[Autowire]`)

## Environment Variables

Configured via `.env` (committed defaults). Override locally in `.env.local` (gitignored).

- `APP_FORCED_PAIRS` — pipe-separated pairs that must play together
- `APP_NAME_ALIASES` — name normalization map
- `DATABASE_URL` — PostgreSQL connection string

## Deployment

Railway.app via `Dockerfile` (production image). Supervisord manages PHP-FPM + Nginx.
Cache is warmed at container startup via `docker/entrypoint.sh`.

## Testing

No test suite currently. If adding tests, use Symfony's test layer (PHPUnit).

## CSS Conventions

- Plain CSS only — no SCSS, no Tailwind, no CSS-in-JS
- Single-property rules on one line: `.selector { property: value; }`
- Multiple properties: one per line, standard multi-line format
- Use `rem` for sizing units
- Mobile-first: base styles target small screens, use `@media (min-width: ...)` for larger breakpoints

## Do Not

- Don't add a JS bundler or build step — assets are served as-is from `public/`
- Don't add a CSS framework
- Don't split `GroupController` into multiple controllers unless asked
- Don't add Doctrine entities unless asked — the app is intentionally stateless (session only)

## Twig Conventions

Base layout is `base.html.twig` with blocks: `title`, `stylesheets`, `body`, `javascripts`.
Two views: `group/index.html.twig` (main form + results) and `group/print.html.twig`.

## Session State

The session stores:
- `rounds` — array of all past rounds (each round is an array of groups)
- `lastRound` — the most recent round's grouping result
- CSRF token — validated on every POST

## Security Headers

`SecurityHeadersListener` sets CSP, X-Frame-Options, and other security headers.
Do not suggest inline `<script>` or `<style>` tags — they will be blocked by CSP.

## Accessibility

The frontend has deliberate a11y implementation. Always preserve:
- ARIA live regions for dynamic announcements
- `sr-only` class for screen-reader-only text
- Skip link to main content
- Semantic heading hierarchy
- ARIA labels on buttons

## Grouping Algorithm (`GroupingService`)

**Constants:** MAX_TABLE_SIZE=4, MIN_TABLE_SIZE=3, MAX_ATTEMPTS=500, THREE_TABLE_PENALTY=5, MAX_PLAYERS=200.

**First round** (`buildFirstRound`): parses fixed pods and the player list, shuffles remaining players once, then calls `assembleGroups`. No scoring — just one random pass.

**Subsequent rounds** (`generateNextRound`): builds a conflict matrix from the previous round (every pair that shared a table is marked), then runs up to 500 shuffle attempts. Each attempt is scored:
- +1 for each pair of players who already played together
- +5 (THREE_TABLE_PENALTY) for each player placed in a 3-player table who was also in a 3-player table the previous round

The lowest-scoring result is kept. Stops early if score reaches 0.

**`assembleGroups` flow:**
1. Pre-reserve forced pair members so fixed pod filling cannot consume them
2. Fill fixed pods from the remaining pool (up to MAX_TABLE_SIZE per pod)
3. Re-merge reserved players back into the pool
4. Seed each forced pair as its own group, filled up to 4 with pool players
5. Distribute the remaining pool via `calculateTableSizes`
6. Return: fixed groups + forced-pair groups + random groups

**`calculateTableSizes` logic** (prefers tables of 4, avoids tables of 2 where possible):
- Remainder 1, n ≥ 9: replace the last two 4-tables with three 3-tables
- Remainder 2, n ≥ 6: replace the last 4-table with two 3-tables
- n = 5: [3, 2] — only case that produces a 2-player table
- Remainder 1 or 2, n < 6: single table of n
- Otherwise: fill with 4s + remainder

Do not simplify this logic without fully understanding the scoring and table-size edge cases.
