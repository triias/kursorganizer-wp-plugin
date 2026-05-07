#!/usr/bin/env node
// Verhindert Release ohne Changelog-Eintrag fuer die neue Version.
// Wird automatisch von `npm version` via "preversion"-Hook in package.json aufgerufen.
//
// Annahme: der Aufrufer hat bereits "npm version <bump>" gestartet, aber das eigentliche
// Bumpen passiert ERST nach preversion. Hier pruefen wir nur, dass die NAECHSTE Version
// im CHANGELOG steht. Da wir den Bump-Typ kennen (npm setzt npm_config_argv), berechnen wir
// die kommende Version selbst.

import { readFileSync } from "node:fs"
import { fileURLToPath } from "node:url"
import { dirname, resolve } from "node:path"

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..")
const pkg = JSON.parse(readFileSync(resolve(root, "package.json"), "utf8"))
const current = pkg.version

// Bump-Typ bestimmen: zuerst aus npm_lifecycle_event (release:patch / release:minor / release:major),
// dann aus npm_config_argv (faellt bei npm v9+ leer aus), dann aus process.argv als Fallback.
let bumpArg = ""
const lifecycle = process.env.npm_lifecycle_event || ""
const lifecycleMatch = lifecycle.match(/^release:(patch|minor|major)$/)
if (lifecycleMatch) {
    bumpArg = lifecycleMatch[1]
}
if (!bumpArg) {
    try {
        const argv = JSON.parse(process.env.npm_config_argv || "{}")
        bumpArg = (argv.original || []).find((a) => /^(patch|minor|major|\d+\.\d+\.\d+)$/.test(a)) || ""
    } catch {
        // ignore
    }
}
if (!bumpArg) {
    bumpArg = process.argv.slice(2).find((a) => /^(patch|minor|major|\d+\.\d+\.\d+)$/.test(a)) || ""
}

if (!bumpArg) {
    // Fallback: kein bekannter Bump-Typ — ueberspringe die Pruefung lieber, statt zu blocken.
    console.log("ℹ preversion: Bump-Typ nicht erkennbar, ueberspringe Changelog-Check.")
    process.exit(0)
}

function nextVersion(curr, bump) {
    if (/^\d+\.\d+\.\d+$/.test(bump)) return bump
    const [maj, min, pat] = curr.split(".").map(Number)
    if (bump === "patch") return `${maj}.${min}.${pat + 1}`
    if (bump === "minor") return `${maj}.${min + 1}.0`
    if (bump === "major") return `${maj + 1}.0.0`
    return null
}

const next = nextVersion(current, bumpArg)
if (!next) {
    console.log(`ℹ preversion: Bump "${bumpArg}" unbekannt, ueberspringe Changelog-Check.`)
    process.exit(0)
}

const changelog = readFileSync(resolve(root, "CHANGELOG.md"), "utf8")
const escapedVersion = next.replace(/\./g, "\\.")
const re = new RegExp(`^##\\s+\\[${escapedVersion}\\]`, "m")
if (!re.test(changelog)) {
    console.error(
        `\n✗ CHANGELOG.md enthaelt keinen Eintrag fuer Version ${next}.\n` +
            `   Bitte zuerst eine Section "## [${next}] - YYYY-MM-DD" hinzufuegen, dann Release wiederholen.\n`
    )
    process.exit(1)
}

console.log(`✓ Changelog-Eintrag fuer ${next} gefunden.`)
