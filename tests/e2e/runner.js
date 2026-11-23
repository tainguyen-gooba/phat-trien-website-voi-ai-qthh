#!/usr/bin/env node

/**
 * tests/e2e/runner.js - Standalone Node.js E2E Test Runner for FASTFOOD Membership Tiers & Loyalty Reward System.
 * Powered by Node.js native `node:test` and `node:test/reporters`.
 */

const fs = require('fs');
const path = require('path');
const { run } = require('node:test');
const { spec, tap } = require('node:test/reporters');
const TestClient = require('./harness/TestClient');

// Resolve test directories
const E2E_ROOT = __dirname;
const TIER1_DIR = path.join(E2E_ROOT, 'tier1-feature-coverage');
const TIER2_DIR = path.join(E2E_ROOT, 'tier2-boundaries');
const TIER3_DIR = path.join(E2E_ROOT, 'tier3-combinations');
const TIER4_DIR = path.join(E2E_ROOT, 'tier4-workflows');
const LEGACY_DIR = path.join(E2E_ROOT, 'legacy-chat');

/**
 * Recursively collects all *.test.js files in a directory.
 * Explicitly ignores legacy-chat and node_modules unless specified.
 */
function collectTestFiles(dir, allowLegacy = false) {
  if (!fs.existsSync(dir)) return [];
  let files = [];
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    if (!allowLegacy && (entry.name === 'legacy-chat' || entry.name === 'node_modules')) {
      continue;
    }
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      files = files.concat(collectTestFiles(fullPath, allowLegacy));
    } else if (entry.isFile() && entry.name.endsWith('.test.js')) {
      files.push(fullPath);
    }
  }
  return files;
}

/**
 * Resolves target test files based on CLI arguments.
 */
function resolveTestFiles(args) {
  if (args.length === 0) {
    // Run all loyalty tests across tiers 1-4 (ignoring legacy-chat)
    return [
      ...collectTestFiles(TIER1_DIR),
      ...collectTestFiles(TIER2_DIR),
      ...collectTestFiles(TIER3_DIR),
      ...collectTestFiles(TIER4_DIR)
    ];
  }

  const selected = [];
  for (const arg of args) {
    if (arg === 'tier1' || arg === 't1') {
      selected.push(...collectTestFiles(TIER1_DIR));
    } else if (arg === 'tier2' || arg === 't2') {
      selected.push(...collectTestFiles(TIER2_DIR));
    } else if (arg === 'tier3' || arg === 't3') {
      selected.push(...collectTestFiles(TIER3_DIR));
    } else if (arg === 'tier4' || arg === 't4') {
      selected.push(...collectTestFiles(TIER4_DIR));
    } else if (arg === 'legacy' || arg === 'chat') {
      selected.push(...collectTestFiles(LEGACY_DIR, true));
    } else if (fs.existsSync(arg)) {
      selected.push(path.resolve(arg));
    } else {
      // Fuzzy filename search across tiers 1-4
      const allLoyalty = [
        ...collectTestFiles(TIER1_DIR),
        ...collectTestFiles(TIER2_DIR),
        ...collectTestFiles(TIER3_DIR),
        ...collectTestFiles(TIER4_DIR)
      ];
      const matched = allLoyalty.filter(f => path.basename(f).includes(arg));
      selected.push(...matched);
    }
  }

  // Deduplicate
  return Array.from(new Set(selected));
}

/**
 * Verifies that the web server is online before running tests.
 */
async function checkServerOnline(baseUrl = 'http://127.0.0.1:5000') {
  try {
    const res = await fetch(`${baseUrl}/index.php`, { signal: AbortSignal.timeout(3000) });
    return res.ok || res.status === 200 || res.status === 302 || res.status === 401;
  } catch {
    return false;
  }
}

async function main() {
  const args = process.argv.slice(2);
  const useTap = args.includes('--tap');
  const filteredArgs = args.filter(a => a !== '--tap');

  const files = resolveTestFiles(filteredArgs);

  console.log('='.repeat(70));
  console.log(' FASTFOOD E2E TEST RUNNER - Loyalty & Membership System');
  console.log('='.repeat(70));

  const baseUrl = process.env.TEST_BASE_URL || 'http://127.0.0.1:5000';
  const serverOnline = await checkServerOnline(baseUrl);
  if (!serverOnline) {
    console.warn(`[WARN] Web server at ${baseUrl} is unreachable.`);
    console.warn('       Please start it using: php -S 127.0.0.1:5000 router.php\n');
  } else {
    console.log(`[INFO] Web server active at ${baseUrl}`);
  }

  console.log(`[INFO] Found ${files.length} test file(s) to execute:`);
  files.forEach(f => console.log(`   - ${path.relative(E2E_ROOT, f)}`));
  console.log('-'.repeat(70));

  if (files.length === 0) {
    console.log('[WARN] No test files found matching criteria.');
    process.exit(0);
  }

  let hasFailures = false;
  let testCount = 0;
  let passCount = 0;
  let failCount = 0;
  let skipCount = 0;

  // Reset local rate limits to avoid artificial test throttling
  TestClient.resetRateLimits();

  // Run sequentially to guarantee zero-flakiness across test sessions
  const testStream = run({
    files,
    concurrency: 1
  });

  testStream.on('test:pass', (t) => {
    // Only count leaf test cases (tests nested inside describe suites)
    if (t.nesting !== undefined && t.nesting > 0) {
      testCount++;
      passCount++;
    }
  });

  testStream.on('test:fail', (t) => {
    hasFailures = true;
    if (t.nesting !== undefined && t.nesting > 0) {
      testCount++;
      failCount++;
    }
  });

  testStream.on('test:skip', () => {
    skipCount++;
  });

  // Pipe to reporter
  if (useTap) {
    testStream.compose(tap).pipe(process.stdout);
  } else {
    testStream.compose(new spec()).pipe(process.stdout);
  }

  testStream.on('end', () => {
    console.log('\n' + '='.repeat(70));
    console.log(` E2E EXECUTION SUMMARY: ${failCount === 0 ? 'ALL PASSED ✓' : 'FAILURES DETECTED ✗'}`);
    console.log(` Total tests: ${testCount} | Passed: ${passCount} | Failed: ${failCount} | Skipped: ${skipCount}`);
    console.log('='.repeat(70));

    if (hasFailures) {
      process.exit(1);
    } else {
      process.exit(0);
    }
  });
}

main().catch(err => {
  console.error('Fatal test runner error:', err);
  process.exit(1);
});
