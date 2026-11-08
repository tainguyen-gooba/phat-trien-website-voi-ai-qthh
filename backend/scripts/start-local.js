const net = require('net');
const path = require('path');
const { spawn } = require('child_process');
const config = require('../src/config/app.config');

const STARTUP_TIMEOUT_MS = 30000;
const MYSQL_CONNECT_TIMEOUT_MS = 1000;
const LOCAL_HOSTS = new Set(['localhost', '127.0.0.1', '::1']);

function canConnectToDatabaseServer() {
  return new Promise((resolve) => {
    const socket = net.createConnection({
      host: config.db.host,
      port: config.db.port,
    });

    const finish = (isOpen) => {
      socket.destroy();
      resolve(isOpen);
    };

    socket.setTimeout(MYSQL_CONNECT_TIMEOUT_MS);
    socket.once('connect', () => finish(true));
    socket.once('error', () => finish(false));
    socket.once('timeout', () => finish(false));
  });
}

function delay(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

function getMySqlPaths() {
  const executable = process.env.MYSQLD_PATH
    || (process.platform === 'win32' ? 'C:\\xampp\\mysql\\bin\\mysqld.exe' : '');
  if (!executable) {
    throw new Error(
      'MySQL is not running. Set MYSQLD_PATH in backend/.env to your local mysqld executable.'
    );
  }

  const defaultsFile = process.env.MYSQLD_DEFAULTS_FILE
    || path.join(path.dirname(executable), 'my.ini');
  if (!fs.existsSync(executable)) {
    throw new Error(`MySQL server executable not found: ${executable}`);
  }
  if (!fs.existsSync(defaultsFile)) {
    throw new Error(`MySQL configuration file not found: ${defaultsFile}`);
  }

  return { executable, defaultsFile };
}

async function ensureLocalMySql() {
  if (!LOCAL_HOSTS.has(config.db.host.toLowerCase())) return;
  if (await canConnectToDatabaseServer()) return;

  const { executable, defaultsFile } = getMySqlPaths();
  console.log(`[Database] MySQL is not running at ${config.db.host}:${config.db.port}. Starting local MySQL...`);

  const mysqlProcess = spawn(executable, [`--defaults-file=${defaultsFile}`], {
    cwd: path.dirname(executable),
    detached: true,
    stdio: 'ignore',
    windowsHide: true,
  });

  let startupError;
  let mysqlExited = false;
  let mysqlExitCode;
  mysqlProcess.once('error', (error) => {
    startupError = error;
  });
  mysqlProcess.once('exit', (code) => {
    mysqlExited = true;
    mysqlExitCode = code;
  });

  const deadline = Date.now() + STARTUP_TIMEOUT_MS;
  while (Date.now() < deadline) {
    if (startupError) {
      throw new Error(`Could not start MySQL: ${startupError.message}`);
    }

    if (mysqlExited) {
      throw new Error(`MySQL exited during startup (code ${mysqlExitCode}). Check the MySQL error log.`);
    }

    if (await canConnectToDatabaseServer()) {
      mysqlProcess.unref();
      console.log('[Database] Local MySQL is ready.');
      return;
    }

    await delay(500);
  }

  mysqlProcess.kill();
  throw new Error(
    `MySQL did not become available at ${config.db.host}:${config.db.port} within ${STARTUP_TIMEOUT_MS / 1000} seconds. Check the MySQL error log.`
  );
}

function startBackend(mode) {
  const backendRoot = path.resolve(__dirname, '..');
  const entryPoint = mode === 'dev'
    ? path.join(backendRoot, 'node_modules', 'nodemon', 'bin', 'nodemon.js')
    : path.join(backendRoot, 'src', 'server.js');
  const args = mode === 'dev' ? [entryPoint, 'src/server.js'] : [entryPoint];
  const backend = spawn(process.execPath, args, {
    cwd: backendRoot,
    stdio: 'inherit',
    windowsHide: true,
  });

  process.once('SIGINT', () => backend.kill('SIGINT'));
  process.once('SIGTERM', () => backend.kill('SIGTERM'));
  backend.once('error', (error) => {
    console.error(`[Backend] Could not start: ${error.message}`);
    process.exitCode = 1;
  });
  backend.once('exit', (code, signal) => {
    process.exitCode = code || (signal ? 1 : 0);
  });
}

async function main() {
  const mode = process.argv[2];
  if (mode !== 'start' && mode !== 'dev') {
    throw new Error('Usage: node scripts/start-local.js <start|dev>');
  }

  await ensureLocalMySql();
  startBackend(mode);
}

main().catch((error) => {
  console.error(`[Startup] ${error.message}`);
  process.exitCode = 1;
});
