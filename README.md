# Release Support Packages

Monorepo for **release-support-backend** (Laravel) and **release-support-frontend** (Vue 3).

Use this during app development and release cycles to capture client errors, submit annotated issue reports, and let configured dev users triage reports and publish version updates.

## Documentation

| Document | Description |
|----------|-------------|
| [packages/backend/README.md](./packages/backend/README.md) | Install, config, API, events, trait |
| [packages/frontend/README.md](./packages/frontend/README.md) | Install, widget, capture, API client |

## Quick start

**Backend**

```bash
composer require kennofizet/release-support-backend
php artisan vendor:publish --tag=release-support-config
php artisan vendor:publish --tag=release-support-migrations
php artisan migrate
```

**Frontend**

```bash
npm install @kennofizet/release-support-frontend
```

```js
installReleaseSupportModule(app, {
  backendUrl: 'https://your-api/api/knf/release-support',
  token: '...',
  appVersion: '1.0.0',
})
```

```vue
<ReleaseSupportWidget />
```