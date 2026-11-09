# Documentation

All project documentation now lives under this directory. The single
root-level report is [FINISHED_ANALYSIS_REPORT.md](../FINISHED_ANALYSIS_REPORT.md);
the project overview and quick start are in [README.md](../README.md).

## Start here

| Audience | Read |
| --- | --- |
| Deploying to shared hosting | [SHARED_HOSTING_DEPLOYMENT.md](deployment/SHARED_HOSTING_DEPLOYMENT.md) |
| Understanding the engine | [ARCHITECTURE.md](backend/ARCHITECTURE.md) |
| Working on the player UI | [FRONTEND_ARCHITECTURE.md](frontend/FRONTEND_ARCHITECTURE.md) |
| Current state and open items | [PROJECT_STATUS.md](project/PROJECT_STATUS.md), [KNOWN_ISSUES.md](project/KNOWN_ISSUES.md) |
| Verification evidence | [FRONTEND_TEST_REPORT.md](reports/FRONTEND_TEST_REPORT.md), root report |

## backend/

| Document | Contents |
| --- | --- |
| [ARCHITECTURE.md](backend/ARCHITECTURE.md) | layering, round flow, service invariants, ADRs |
| [ENGINE_SPECIFICATION.md](backend/ENGINE_SPECIFICATION.md) | engine contract and behaviour |
| [API_SPECIFICATION.md](backend/API_SPECIFICATION.md) | every HTTP route, envelope, errors, auth |
| [DATABASE_SCHEMA.md](backend/DATABASE_SCHEMA.md) | tables, columns, indexes, migration runner |
| [GAME_SDK.md](backend/GAME_SDK.md) | adding a game without touching the core |
| [FRAMEWORK_SLOT.md](backend/FRAMEWORK_SLOT.md) / [FRAMEWORK_CARD.md](backend/FRAMEWORK_CARD.md) / [FRAMEWORK_ROULETTE.md](backend/FRAMEWORK_ROULETTE.md) | the three game frameworks |
| [ADMIN_PANEL.md](backend/ADMIN_PANEL.md) | probability governor and admin API |
| [TESTING_STRATEGY.md](backend/TESTING_STRATEGY.md) | what the suite proves and does not prove |
| [SECURITY_AUDIT.md](backend/SECURITY_AUDIT.md) | server-side security review (2026-11-07) |
| [games/](backend/games/) | one specification per target game (18 files) |

## frontend/

| Document | Contents |
| --- | --- |
| [FRONTEND_ARCHITECTURE.md](frontend/FRONTEND_ARCHITECTURE.md) | modules, router, state, API flow |
| [DESIGN_SYSTEM.md](frontend/DESIGN_SYSTEM.md) | tokens, type, components, motion, a11y |
| [COMPONENT_CATALOG.md](frontend/COMPONENT_CATALOG.md) | every component and state |
| [ADMIN_PANEL.md](frontend/ADMIN_PANEL.md) | standalone operator console: access, pages, game controls, audit |
| [GAME_INTERFACE_IMPLEMENTATION.md](frontend/GAME_INTERFACE_IMPLEMENTATION.md) | the four interfaces and all 22 modules |
| [AH77_SOURCE_INVENTORY.md](frontend/AH77_SOURCE_INVENTORY.md) | every file in the supplied mirror, with hashes |
| [REFERENCE_WEBSITE_ANALYSIS.md](frontend/REFERENCE_WEBSITE_ANALYSIS.md) | what the reference is and what was reused |
| [ASSET_MAPPING.md](frontend/ASSET_MAPPING.md) | AH77_SOURCE asset mapping → local WebP game/banner art |
| [RESOURCE_GAP_ANALYSIS.md](frontend/RESOURCE_GAP_ANALYSIS.md) | reference vs engine catalogue |
| [MISSING_GAME_ASSETS.md](frontend/MISSING_GAME_ASSETS.md) | placeholder art and replacement paths |
| [SECURITY_AUDIT.md](frontend/SECURITY_AUDIT.md) | audit of the untrusted reference export |
| [IMPLEMENTATION_LOG.md](frontend/IMPLEMENTATION_LOG.md) | frontend phase chronology |

## deployment/

| Document | Contents |
| --- | --- |
| [SHARED_HOSTING_DEPLOYMENT.md](deployment/SHARED_HOSTING_DEPLOYMENT.md) | upload `htdocs/`, configure, import, upgrade, troubleshoot |
| [PHP_MYSQL_COMPATIBILITY.md](deployment/PHP_MYSQL_COMPATIBILITY.md) | versions, extensions, DB features, hosting limits |
| [PRODUCTION_SECURITY.md](deployment/PRODUCTION_SECURITY.md) | controls, operator checklist, residual risks |

## project/

| Document | Contents |
| --- | --- |
| [PROJECT_STATUS.md](project/PROJECT_STATUS.md) | current phase, environment, publication record |
| [GAME_DEVELOPMENT_ROADMAP.md](project/GAME_DEVELOPMENT_ROADMAP.md) | phase board |
| [DEVELOPMENT_LOG.md](project/DEVELOPMENT_LOG.md) | chronological development history |
| [KNOWN_ISSUES.md](project/KNOWN_ISSUES.md) | open and resolved limitations |
| [CHANGELOG.md](project/CHANGELOG.md) | release-level changes |
| [RESEARCH_NOTES.md](project/RESEARCH_NOTES.md) | reference and game research |
| [AUTONOMOUS_EXECUTION_PLAN.md](project/AUTONOMOUS_EXECUTION_PLAN.md) | original phase directive (historical) |

## reports/

| Document | Contents |
| --- | --- |
| [FRONTEND_FINAL_REPORT.md](reports/FRONTEND_FINAL_REPORT.md) | frontend delivery report |
| [FRONTEND_TEST_REPORT.md](reports/FRONTEND_TEST_REPORT.md) | commands, results, defects, limitations |

## Conventions

* Documents describe the code as it is, including what was not tested.
* Relative links are kept valid when files move; run a link check before
  committing documentation changes.
* Historical logs (`DEVELOPMENT_LOG.md`, `AUTONOMOUS_EXECUTION_PLAN.md`) refer
  to documents by their old root filenames in places; use this index for the
  current location.
