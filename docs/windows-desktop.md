# acc263 Windows Desktop App

This project includes an Electron wrapper that starts Laravel locally and opens it in a desktop window.

## Requirements

- Windows 10 or newer
- PHP 8.2 or newer available on `PATH`
- Node.js 20 or newer
- Composer dependencies installed in `vendor/`

## Development

```sh
npm install
npm run desktop
```

The desktop app starts Laravel on `http://127.0.0.1:8726`.

## Build Windows Installers

Run this on a Windows machine:

```sh
npm install
npm run desktop:dist
```

The generated installer and portable app are written to `dist/windows/`.

## Notes

The packaged app includes the Laravel source, built frontend assets, `.env`, and `vendor/`. Do not distribute a production app with development secrets in `.env`.
