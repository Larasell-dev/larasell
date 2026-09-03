import { execFileSync } from 'node:child_process'

const evaluate = String.raw`
$messages = require $argv[1];

if (!is_array($messages)) {
    fwrite(STDERR, 'Translation files must return an array.');
    exit(1);
}

echo json_encode($messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
`

export function transformTranslation(file: string): string {
  try {
    const messages = execFileSync('php', ['-r', evaluate, file], {
      encoding: 'utf8',
      maxBuffer: 10 * 1024 * 1024,
    })

    return `export default ${messages};\n`
  } catch (error) {
    const detail = error instanceof Error ? error.message : String(error)

    throw new Error(`Unable to compile Laravel translations from ${file}: ${detail}`)
  }
}
