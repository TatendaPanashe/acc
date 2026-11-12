const { app, BrowserWindow, dialog } = require('electron');
const { spawn } = require('child_process');
const net = require('net');
const path = require('path');

const APP_PORT = process.env.ACC263_DESKTOP_PORT || 8726;
const APP_HOST = '127.0.0.1';
const APP_URL = `http://${APP_HOST}:${APP_PORT}`;

let phpServer = null;
let mainWindow = null;

function waitForPort(port, host, timeoutMs = 30000) {
    const startedAt = Date.now();

    return new Promise((resolve, reject) => {
        const check = () => {
            const socket = net.createConnection({ port, host }, () => {
                socket.end();
                resolve();
            });

            socket.on('error', () => {
                socket.destroy();

                if (Date.now() - startedAt > timeoutMs) {
                    reject(new Error(`Laravel did not start on ${host}:${port}`));
                    return;
                }

                setTimeout(check, 250);
            });
        };

        check();
    });
}

function startLaravel() {
    const projectRoot = app.isPackaged
        ? app.getAppPath()
        : path.resolve(__dirname, '..');

    phpServer = spawn('php', [
        'artisan',
        'serve',
        '--host',
        APP_HOST,
        '--port',
        String(APP_PORT),
    ], {
        cwd: projectRoot,
        env: {
            ...process.env,
            APP_ENV: process.env.APP_ENV || 'production',
            APP_DEBUG: process.env.APP_DEBUG || 'false',
        },
        stdio: app.isPackaged ? 'ignore' : 'inherit',
        windowsHide: true,
    });

    phpServer.on('exit', () => {
        phpServer = null;
    });
}

async function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1280,
        height: 820,
        minWidth: 1024,
        minHeight: 700,
        title: 'acc263',
        backgroundColor: '#f5f7fb',
        autoHideMenuBar: true,
        webPreferences: {
            contextIsolation: true,
            nodeIntegration: false,
        },
    });

    try {
        startLaravel();
        await waitForPort(APP_PORT, APP_HOST);
        await mainWindow.loadURL(APP_URL);
    } catch (error) {
        dialog.showErrorBox(
            'acc263 failed to start',
            `${error.message}\n\nMake sure PHP 8.2 or newer is installed and available on PATH.`
        );
        app.quit();
    }
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
    app.quit();
});

app.on('before-quit', () => {
    if (phpServer) {
        phpServer.kill();
        phpServer = null;
    }
});
