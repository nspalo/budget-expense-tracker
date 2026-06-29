---
inclusion: always
---

# Project Conventions

## Overview

Budget & Expense Tracker — a personal finance app with a single continuous ledger and dual-axis tracking (billing period vs payment date).

## Tech Stack

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| Backend | Laravel | 12.x | Application framework |
| Language | PHP | 8.4 | Server-side logic |
| API | Lighthouse PHP | latest | GraphQL schema-first server |
| Frontend | Vue.js | 3.x | SPA framework |
| Styling | Tailwind CSS | 4.x | Utility-first CSS |
| Build | Vite | 7.x | Frontend bundling and HMR |
| State | Pinia | latest | Vue state management |
| Types | TypeScript | strict | Frontend type safety |
| Database | MySQL | 8.0 | Relational data store |
| Auth | Laravel Sanctum | latest | Token-based API auth |
| Testing (BE) | PHPUnit | 11.x | Backend tests |
| Testing (FE) | Vitest | latest | Frontend tests |
| Linting | Laravel Pint | latest | PSR-12 formatting |
| Infrastructure | Docker Compose | - | Containerized dev/prod |

### Package Selection Philosophy
- Prefer well-maintained, first-party Laravel packages when available
- Pin exact versions in `composer.lock` and `package-lock.json`
- Avoid packages that duplicate what Laravel already provides
- Evaluate alternatives by: maintenance activity, community size, Laravel compatibility

### Key Packages to Install (not yet in composer.json)
- `nuwave/lighthouse` — GraphQL server
- `laravel/sanctum` — API authentication
- `owen-it/laravel-auditing` or custom audit events — audit trail
- `vue`, `pinia`, `vue-router` — frontend SPA
- `@graphql-codegen/*` — (optional) generate TS types from GraphQL schema

## Git Flow

- **Branches:** `master` (stable) ← `development` (integration) ← `feature/*` branches
- **PRs only** — no direct pushes to master or development
- **Squash merge** on feature branches into development
- **Regular merge** from development into master (preserves integration history)
- **Commit style:** Conventional Commits with project code prefix

### Project Code: BETA (Budget & Expense Tracker App)

All branches, commits, and issues use the `BETA` project code for traceability.

### Commit Format
```
type(BETA-XXX): short description
```

| Prefix | Use for |
|--------|---------|
| `feat(BETA-XXX):` | New features |
| `fix(BETA-XXX):` | Bug fixes |
| `refactor(BETA-XXX):` | Code restructuring (no behavior change) |
| `chore(BETA-XXX):` | Dependencies, config, tooling |
| `docs(BETA-XXX):` | Documentation only |
| `test(BETA-XXX):` | Adding or fixing tests |
| `style(BETA-XXX):` | Formatting (no logic change) |
| `perf(BETA-XXX):` | Performance improvements |

Examples:
- `feat(BETA-005): add transaction CRUD service`
- `chore(BETA-001): add kiro steering files`
- `fix(BETA-012): correct budget remaining calculation`

### Branch Naming
Format: `{type}/BETA-{issue_number}-{short-description}`

| Prefix | Use for |
|--------|---------|
| `config/` | Project setup, tooling, CI, Docker config, steering files |
| `design/` | Specs, requirements, tech design, architecture decisions |
| `feature/` | Implementation of new functionality |
| `bugfix/` | Fixing broken behavior |

Examples:
- `config/BETA-002-project-conventions`
- `design/BETA-003-feature-requirements`
- `design/BETA-004-technical-design`
- `feature/BETA-005-database-schema`
- `feature/BETA-006-auth-module`
- `bugfix/BETA-012-budget-calculation`

### Issue Tracking
- Use GitHub Projects (Kanban board) for task management
- GitHub Issues numbered sequentially, referenced in branches and commits
- PRs reference issues: `Closes #5` in PR description

### Docker & Config Naming
- `COMPOSE_PROJECT_NAME`: `budget_tracker` (not `docker_laravel`)
- Makefile header: "Budget & Expense Tracker (BETA)"
- Container prefix: `budget_tracker_nginx`, `budget_tracker_php`, etc.

## File Structure

```
budget-expense-tracker/
├── .kiro/
│   ├── steering/           # AI steering files
│   └── specs/              # Feature specs (requirements → design → tasks)
├── docker/
│   ├── containers/         # Dockerfiles per service
│   ├── environments/       # config.env, dev.env, prod.env
│   ├── docker-compose.yml
│   ├── docker-compose.dev.yml
│   └── docker-compose.prod.yml
├── src/                    # Laravel application root
│   ├── app/
│   │   ├── Enums/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Repositories/
│   │   ├── Actions/
│   │   ├── DTOs/
│   │   ├── GraphQL/
│   │   │   ├── Queries/
│   │   │   └── Mutations/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Traits/
│   │   └── Exceptions/
│   ├── database/
│   │   ├── migrations/
│   │   ├── seeders/
│   │   └── factories/
│   ├── graphql/            # .graphql schema files
│   │   ├── schema.graphql
│   │   ├── types/
│   │   ├── queries/
│   │   └── mutations/
│   ├── routes/
│   ├── resources/
│   │   └── js/            # Vue.js SPA
│   │       ├── components/
│   │       ├── pages/
│   │       ├── stores/
│   │       ├── composables/
│   │       ├── types/
│   │       └── utils/
│   └── tests/
│       ├── Feature/
│       └── Unit/
├── Makefile
├── README.md
└── .gitattributes
```

## Environment Strategy

- **Development:** Volume mounts, hot-reload, exposed ports, debug configs
- **Production:** Multi-stage builds, no source mounts, optimized images
- **Testing:** Runs inside Docker via `make test` (uses same PHP container)

## CI/CD (Planned)

- GitHub Actions
- PR validation: lint (Pint) + tests (PHPUnit + Vitest) on `master` and `development`
- Push on `master`: build production images, tag release
