#!/usr/bin/env node
// Synchronisiert die Version aus package.json in die anderen Dateien des Plugins.
// Wird automatisch von `npm version` via "version"-Hook in package.json aufgerufen.

import { readFileSync, writeFileSync } from "node:fs"
import { fileURLToPath } from "node:url"
import { dirname, resolve } from "node:path"

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..")
const pkg = JSON.parse(readFileSync(resolve(root, "package.json"), "utf8"))
const version = pkg.version

if (!/^\d+\.\d+\.\d+/.test(version)) {
    console.error(`✗ package.json hat ungueltige Version: ${version}`)
    process.exit(1)
}

function patch(file, replacements) {
    const path = resolve(root, file)
    const before = readFileSync(path, "utf8")
    let after = before
    for (const { pattern, replacement, label } of replacements) {
        if (!pattern.test(after)) {
            console.error(`✗ ${file}: Pattern fuer "${label}" nicht gefunden`)
            process.exit(1)
        }
        // Pattern als RegExp ist nach .test() im lastIndex-State — neu erzeugen, falls global
        after = after.replace(pattern, replacement)
    }
    if (after === before) return false
    writeFileSync(path, after)
    return true
}

const phpChanged = patch("kursorganizer-wp-plugin.php", [
    {
        label: "Plugin-Header Version:",
        pattern: /^(Version:\s*)\S+/m,
        replacement: `$1${version}`,
    },
    {
        label: "KURSORGANIZER_VERSION-Konstante",
        pattern: /(define\('KURSORGANIZER_VERSION',\s*')[^']+('\))/,
        replacement: `$1${version}$2`,
    },
])

const readmeChanged = patch("README.md", [
    {
        label: "Stable tag",
        pattern: /^(Stable tag:\s*)\S+/m,
        replacement: `$1${version}`,
    },
])

console.log(`✓ Version ${version} synchronisiert (${[phpChanged && "plugin.php", readmeChanged && "README.md"].filter(Boolean).join(", ")})`)
