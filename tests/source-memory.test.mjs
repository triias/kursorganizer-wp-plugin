// Prüft den Herkunfts-Merker assets/js/source-memory.js, den das Plugin in den Kopf jeder Seite schreibt.
// Start: node --test tests/source-memory.test.mjs
import assert from "node:assert/strict"
import { readFileSync } from "node:fs"
import { test } from "node:test"

const script = readFileSync(new URL("../assets/js/source-memory.js", import.meta.url), "utf8")

/** Seite mit Adresse und Referrer laden; liefert den danach gemerkten Wert (undefined = nichts gemerkt). */
function remembered(url, referrer, before) {
    const page = new URL(url)
    const store = new Map(before ? [["koReferrer", before]] : [])
    const window = {
        location: { search: page.search, hostname: page.hostname },
        sessionStorage: { setItem: (key, value) => store.set(key, value) },
    }
    new Function("window", "document", script)(window, { referrer })
    return store.get("koReferrer")
}

test("merkt den fremden Host, von dem der Besucher kam, nicht die eigene Website", () => {
    assert.equal(remembered("https://www.schule.de/", "https://www.google.com/"), "www.google.com")
    assert.equal(remembered("https://www.schule.de/kurse/", "https://schule.de/"), undefined)
    assert.equal(remembered("https://www.schule.de/", ""), undefined)
})

test("lässt den gemerkten Wert beim Klick innerhalb der Website stehen", () => {
    assert.equal(remembered("https://www.schule.de/kurse/", "https://www.schule.de/", "www.bing.com"), "www.bing.com")
})

test("merkt utm_source auch auf einer Seite ohne Web Modul, vor dem Referrer", () => {
    assert.equal(remembered("https://www.schule.de/?utm_source=newsletter", ""), "newsletter")
    assert.equal(remembered("https://www.schule.de/?utm_source=chatgpt.com", "https://chatgpt.com/"), "chatgpt.com")
    // Unbrauchbare Werte zählen nicht, der Referrer bleibt
    assert.equal(remembered("https://www.schule.de/?utm_source=%3Cscript%3E", "https://www.bing.com/"), "www.bing.com")
    assert.equal(remembered("https://www.schule.de/?utm_source=", "https://www.bing.com/"), "www.bing.com")
})

test("erkennt bezahlte Anzeigen am Namen der Klick-Kennung, vor utm_source und Referrer, und merkt die Kennung nicht", () => {
    assert.equal(remembered("https://www.schule.de/?utm_source=google&gclid=EAIa-geheim", "https://www.google.com/"), "gclid")
    assert.equal(remembered("https://www.schule.de/?wbraid=abc", ""), "wbraid")
    assert.equal(remembered("https://www.schule.de/?msclkid=abc", "https://www.bing.com/"), "msclkid")
    assert.equal(remembered("https://www.schule.de/?gclid=", "https://www.google.com/"), "www.google.com")
})

test("meldet die Android-App, aus der der Link geöffnet wurde", () => {
    assert.equal(
        remembered("https://www.schule.de/", "android-app://com.google.android.googlequicksearchbox/https/www.google.com"),
        "android-app.com.google.android.googlequicksearchbox"
    )
})

test("nimmt fbclid und ttclid nur, wenn sonst nichts bekannt ist", () => {
    assert.equal(remembered("https://www.schule.de/?fbclid=IwAR0-geheim", ""), "fbclid")
    assert.equal(remembered("https://www.schule.de/?ttclid=xyz", ""), "ttclid")
    assert.equal(remembered("https://www.schule.de/?fbclid=IwAR0-geheim", "https://l.instagram.com/?u=x"), "l.instagram.com")
    assert.equal(remembered("https://www.schule.de/?utm_source=fb&fbclid=IwAR0-geheim", ""), "fb")
})

test("merkt nichts beim Rücksprung über den Login-Link aus einer Mail: die Seite davor ist das Mail-Programm", () => {
    const loginLink = "https://www.schule.de/kurse/?internalPath=URL_LOGIN_COMPLETE_PASSWORDLESS&code=123456"
    assert.equal(remembered(loginLink, "https://www.google.com/url?q=x"), undefined)
    assert.equal(remembered(loginLink, "android-app://com.google.android.gm/"), undefined)
    // Ein schon gemerkter Wert bleibt stehen
    assert.equal(remembered(loginLink, "https://www.google.com/", "www.bing.com"), "www.bing.com")
})

test("wirft nie, auch nicht ohne sessionStorage oder mit kaputtem Referrer", () => {
    new Function("window", "document", script)({ location: { search: "", hostname: "x.de" } }, { referrer: "https://www.google.com/" })
    assert.equal(remembered("https://www.schule.de/", "https://"), undefined)
})
