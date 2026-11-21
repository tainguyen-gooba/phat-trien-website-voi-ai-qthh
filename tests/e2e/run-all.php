<?php
/**
 * tests/e2e/run-all.php - Master PHP Test Runner Wrapper for FASTFOOD E2E Modernization Suite.
 *
 * Usage:
 *   php tests/e2e/run-all.php
 *   php tests/e2e/run-all.php tier1
 *   php tests/e2e/run-all.php tier2
 */

$startTime = microtime(true);

echo "======================================================================\n";
echo "   FASTFOOD E2E TEST SUITE: MODERNIZATION VERIFICATION (PHP WRAPPER)  \n";
echo "======================================================================\n";

// 1. Check PHP Version
echo "[PHP] Version: " . PHP_VERSION . " (CLI)\n";

// 2. Check Node.js runtime
$nodeVer = @shell_exec('node -v');
if (!$nodeVer) {
    echo "[ERROR] Node.js is required to execute the E2E test harness.\n";
    exit(1);
}
echo "[NODE] Runtime: " . trim($nodeVer) . "\n";

// 3. Forward CLI arguments to runner.js
$cliArgs = array_slice($argv, 1);
$argsString = implode(' ', array_map('escapeshellarg', $cliArgs));

$runnerScript = __DIR__ . DIRECTORY_SEPARATOR . 'runner.js';
if (!file_exists($runnerScript)) {
    echo "[ERROR] Runner script not found at {$runnerScript}\n";
    exit(1);
}

// 4. Execute runner.js via passthru for robust cross-platform streaming without pipe deadlocks
$cmd = 'node ' . escapeshellarg($runnerScript) . ' ' . $argsString;

$exitCode = 0;
passthru($cmd, $exitCode);

$duration = round(microtime(true) - $startTime, 3);

echo "\n======================================================================\n";
echo " PHP WRAPPER SUMMARY: " . ($exitCode === 0 ? "PASSED (0)" : "FAILURES DETECTED ({$exitCode})") . "\n";
echo " Total Wall-Clock Execution Time: {$duration}s\n";
echo "======================================================================\n";

exit($exitCode);
