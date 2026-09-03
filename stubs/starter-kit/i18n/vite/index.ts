import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import type { ModuleNode, Plugin, ViteDevServer } from 'vite'
import { discoverTranslations, type TranslationFile } from './discover'
import { transformTranslation } from './transform'
import {
  publicRuntimeId,
  resolvedRuntimeId,
  resolvedTranslationPrefix,
  runtimeModule,
  translationId,
  translationPrefix,
} from './virtualModules'

export type LaravelI18nOptions = {
  langPath?: string
}

export default function laravelI18n(options: LaravelI18nOptions = {}): Plugin {
  let root = process.cwd()
  let langPath = resolve(root, options.langPath ?? 'lang')
  let files: TranslationFile[] = []
  const runtimeImport = fileURLToPath(new URL('../runtime/index.ts', import.meta.url))

  const refresh = () => {
    files = discoverTranslations(langPath)
  }

  const find = (locale: string, namespace: string) => files.find(
    (file) => file.locale === locale && file.namespace === namespace,
  )

  const invalidateRuntime = (server: ViteDevServer) => {
    refresh()
    const module = server.moduleGraph.getModuleById(resolvedRuntimeId)

    if (module) {
      server.moduleGraph.invalidateModule(module)
    }

    server.ws.send({ type: 'full-reload' })
  }

  return {
    name: 'laravel-i18n',

    configResolved(config) {
      root = config.root
      langPath = resolve(root, options.langPath ?? 'lang')
      refresh()
    },

    configureServer(server) {
      server.watcher.add(resolve(langPath, '**/*.php'))
      server.watcher.on('add', (file) => file.startsWith(langPath) && invalidateRuntime(server))
      server.watcher.on('unlink', (file) => file.startsWith(langPath) && invalidateRuntime(server))
    },

    resolveId(id) {
      if (id === publicRuntimeId) {
        return resolvedRuntimeId
      }

      if (id.startsWith(translationPrefix)) {
        return `\0${id}`
      }
    },

    load(id) {
      if (id === resolvedRuntimeId) {
        refresh()
        return runtimeModule(files, runtimeImport)
      }

      if (!id.startsWith(resolvedTranslationPrefix)) {
        return
      }

      const [locale, namespace] = id.slice(resolvedTranslationPrefix.length).split('/', 2)
      const translation = find(locale, namespace)

      if (!translation) {
        throw new Error(`Unknown Laravel translation module [${locale}/${namespace}].`)
      }

      this.addWatchFile(translation.file)

      return transformTranslation(translation.file)
    },

    handleHotUpdate(context) {
      const translation = files.find((file) => file.file === context.file)

      if (!translation) {
        return
      }

      const module = context.server.moduleGraph.getModuleById(`\0${translationId(translation.locale, translation.namespace)}`)

      if (module) {
        context.server.moduleGraph.invalidateModule(module)
      }

      context.server.ws.send({ type: 'full-reload' })

      return module ? [module as ModuleNode] : []
    },
  }
}

export { laravelI18n }
