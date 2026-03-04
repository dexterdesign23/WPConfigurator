# Printer Builder Configurator

WordPress plugin skeleton for a schema-driven printer builder configurator.

## Structure

- `printer-builder-configurator.php` - plugin bootstrap, hooks, constants, and guarded includes.
- `includes/` - CPT registration, schema storage, REST routes, and rule engine.
- `admin/` - admin menu/page bootstrap and built asset target (`admin/assets`).
- `public/` - shortcode bootstrap and built asset target (`public/assets`).
- `assets/` - React/Vite source code.
- `package.json` + `vite.config.js` - frontend build configuration.

## Development

```bash
npm install
npm run build
```

After build, copy generated bundles to:
- `admin/assets/`
- `public/assets/`

## Activation behavior

On activation, the plugin seeds a default empty schema in the WordPress options table if one does not yet exist.
