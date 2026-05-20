#!/usr/bin/env node
// Automated release: bump versions, build, commit, tag, push.
// GitHub Action (.github/workflows/release.yml) takes over on the pushed tag
// to package the ZIP and publish the GitHub Release.
//
// Usage:
//   npm run release            # patch bump (1.3.6 -> 1.3.7)
//   npm run release minor      # 1.3.6 -> 1.4.0
//   npm run release major      # 1.3.6 -> 2.0.0
//   npm run release 1.5.2      # explicit version

import { execSync, spawnSync } from "node:child_process";
import { readFileSync, writeFileSync } from "node:fs";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(__dirname, "..");
process.chdir(repoRoot);

const RELEASE_BRANCH = "main";
const PLUGIN_FILE = "designstudio-flow.php";
const PKG_FILE = "package.json";

function sh(cmd, opts = {}) {
  return execSync(cmd, { encoding: "utf8", stdio: ["ignore", "pipe", "pipe"], ...opts }).trim();
}

function shInherit(cmd) {
  const result = spawnSync(cmd, { shell: true, stdio: "inherit" });
  if (result.status !== 0) {
    fail(`Command failed: ${cmd}`);
  }
}

function fail(msg) {
  console.error(`\n❌ ${msg}\n`);
  process.exit(1);
}

function info(msg) {
  console.log(`→ ${msg}`);
}

function bumpSemver(current, kind) {
  const m = current.match(/^(\d+)\.(\d+)\.(\d+)$/);
  if (!m) fail(`Cannot parse current version: ${current}`);
  let [major, minor, patch] = m.slice(1).map(Number);
  if (kind === "major") return `${major + 1}.0.0`;
  if (kind === "minor") return `${major}.${minor + 1}.0`;
  if (kind === "patch") return `${major}.${minor}.${patch + 1}`;
  if (/^\d+\.\d+\.\d+$/.test(kind)) return kind;
  fail(`Invalid bump arg: "${kind}". Use patch | minor | major | x.y.z`);
}

function readPkg() {
  return JSON.parse(readFileSync(PKG_FILE, "utf8"));
}

function writePkg(pkg) {
  writeFileSync(PKG_FILE, JSON.stringify(pkg, null, 2) + "\n");
}

function updatePhpVersions(nextVersion) {
  const src = readFileSync(PLUGIN_FILE, "utf8");
  let updated = src.replace(
    /^(\s*\*\s*Version:\s*).*$/m,
    `$1${nextVersion}`,
  );
  updated = updated.replace(
    /define\(\s*'DSF_VERSION'\s*,\s*'[^']*'\s*\)/,
    `define( 'DSF_VERSION', '${nextVersion}' )`,
  );
  if (updated === src) {
    fail(`Failed to update version strings in ${PLUGIN_FILE}`);
  }
  writeFileSync(PLUGIN_FILE, updated);
}

function ensureRemoteTagFree(tag) {
  try {
    const out = sh(`git ls-remote --tags origin refs/tags/${tag}`);
    if (out) fail(`Tag ${tag} already exists on origin. Bump to a new version.`);
  } catch {
    // network/remote error — surface but don't hard-fail; the push step will catch it.
  }
}

// ── Main ──────────────────────────────────────────────────────────────────────

const bumpArg = (process.argv[2] || "patch").toLowerCase();

// Branch check
const branch = sh("git rev-parse --abbrev-ref HEAD");
if (branch !== RELEASE_BRANCH) {
  fail(`Releases must run on '${RELEASE_BRANCH}'. Currently on '${branch}'.`);
}

// Make sure we're up to date with origin so the release commit lands on top.
info("Fetching origin…");
shInherit("git fetch origin --tags");
const behind = sh(`git rev-list --count HEAD..origin/${RELEASE_BRANCH}`);
if (Number(behind) > 0) {
  fail(`Local '${RELEASE_BRANCH}' is ${behind} commit(s) behind origin. Pull first.`);
}

const pkg = readPkg();
const current = pkg.version;
const next = bumpSemver(current, bumpArg);
const tag = `v${next}`;

if (current === next) fail(`Computed version equals current (${current}).`);

ensureRemoteTagFree(tag);

console.log(`\n📦 Releasing ${current} → ${next}\n`);

// Bump versions
info(`Bumping ${PKG_FILE}`);
pkg.version = next;
writePkg(pkg);
// Keep package-lock in sync without creating a tag/commit.
shInherit(`npm version ${next} --no-git-tag-version --allow-same-version >/dev/null`);

info(`Bumping ${PLUGIN_FILE} (header + DSF_VERSION)`);
updatePhpVersions(next);

// Build production assets (these need to be committed for the GH Action).
info("Building production assets (npm run build)…");
shInherit("npm run build");

// Stage everything (bundles any pre-existing dirty tree into the release commit).
info("Staging changes…");
shInherit("git add -A");

const staged = sh("git diff --cached --name-only");
if (!staged) fail("Nothing staged — bump did not change any files?");

info("Creating release commit…");
shInherit(`git commit -m "Release ${tag}"`);

info(`Creating tag ${tag}…`);
shInherit(`git tag -a ${tag} -m "Release ${tag}"`);

info(`Pushing ${RELEASE_BRANCH} and ${tag} to origin…`);
shInherit(`git push origin ${RELEASE_BRANCH}`);
shInherit(`git push origin ${tag}`);

const repoSlug = sh("git config --get remote.origin.url")
  .replace(/^git@github\.com:/, "")
  .replace(/^https:\/\/github\.com\//, "")
  .replace(/\.git$/, "");

console.log(`\n✅ Released ${tag}`);
console.log(`   GitHub Actions will package the ZIP and publish the release.`);
console.log(`   Watch: https://github.com/${repoSlug}/actions`);
console.log(`   Release: https://github.com/${repoSlug}/releases/tag/${tag}\n`);
