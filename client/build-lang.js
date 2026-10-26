// Generates client/lang/*.js from client/lang/src/*.json, in the format silverstripe/admin expects
import { readdirSync, readFileSync, writeFileSync } from 'node:fs'

const dir = new URL('./lang/', import.meta.url)

for (const file of readdirSync(new URL('./src/', dir)).filter((name) => name.endsWith('.json'))) {
  const locale = file.replace(/\.json$/, '')
  const dictionary = JSON.parse(readFileSync(new URL(`./src/${file}`, dir), 'utf8'))
  const entries = Object.entries(dictionary)
    .map(([key, value]) => `    ${JSON.stringify(key)}: ${JSON.stringify(value)}`)
    .join(',\n')

  writeFileSync(
    new URL(`./${locale}.js`, dir),
    `// This file is generated from client/lang/src/${file} by \`npm run build:lang\`, edit that instead.
if (typeof(ss) === 'undefined' || typeof(ss.i18n) === 'undefined') {
  if (typeof(console) !== 'undefined') { // eslint-disable-line no-console
    console.error('Class ss.i18n not defined');  // eslint-disable-line no-console
  }
} else {
  ss.i18n.addDictionary('${locale}', {
${entries}
});
}
`,
  )
}
