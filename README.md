# PBN Hub Child

Lightweight WordPress plugin installed on each child PBN site. Exposes a small REST API the central [PBN Hub](https://github.com/OppositeX/pbn-hub) calls into, authenticated by a per-site bearer token.

## Install

1. From the Hub: **Settings → Child Plugin Distribution → Download**.
2. On the child WP site: **Plugins → Add New → Upload Plugin** → select the zip → install + activate.
3. Go to **Settings → PBN Hub Child** on the child site.
4. Paste the **Hub URL** (e.g. `https://pbn.d3v.co.il`) and the **token** the Hub generated for this domain.
5. Click **Save & Handshake**. The site will turn green on the Hub's Domains page within seconds.

## REST API

Namespace: `pbn-hub-child/v1`

| Endpoint | Method | Auth |
|---|---|---|
| `/whoami` | GET | bearer |
| `/categories` | GET | bearer |
| `/factory-publish` | POST | bearer |
| `/posts/{id}/status` | POST | bearer |
| `/analytics?range=30d` | GET | bearer |
| `/health` | GET | public (signature only) |

`Authorization: Bearer <token>` is required on every authenticated route. Tokens are issued by the Hub.

## Auto-update

Hooked to GitHub releases of `OppositeX/pbn-hub-child`. New tags surface as plugin updates inside WordPress.

If the repo is private, set `pbn_hub_child_github_token` to a personal access token (read scope) so the updater can hit the GitHub API.
