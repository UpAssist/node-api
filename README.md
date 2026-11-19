# UpAssist.NodeApi

Read and write services for Neos nodes (`UpAssist\NodeApi\Services\Node\ReadService` and `WriteService`), a `ContentContextService`, a Fluid ViewHelper and an optional `nodeapi/` route.

## Compatibility

| Version | Neos | Status |
| --- | --- | --- |
| 8.x | 8.3, 8.4 | maintained |

Neos 9 is not supported: the services are built on the Neos 8 Content Repository (`NodeDataRepository`, `ContentContextFactory`, `NodeTemplate`), which no longer exists in Neos 9.

## Installation

The package is not on Packagist. Add the repository to your project's `composer.json`:

```json
"repositories": [
    {"type": "vcs", "url": "https://github.com/UpAssist/node-api.git"}
]
```

```bash
composer require upassist/nodeapi:^8.0
```

## The `nodeapi/` route

`Configuration/Routes.yaml` is not included automatically. To use `nodeapi/node/new` and `nodeapi/node/create`, include the routes in your `Settings.yaml` and make sure an authentication provider covers `UpAssist\NodeApi\Controller\.*` (for example a `requestPatterns` entry on `Neos.Neos:Backend` or your frontend login provider):

```yaml
Neos:
  Flow:
    mvc:
      routes:
        'UpAssist.NodeApi':
          position: 'before Neos.Neos'
```
