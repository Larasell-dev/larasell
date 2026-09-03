import { router, usePage } from '@inertiajs/react'
import { useTranslation } from '@larasell-dev/inertia-i18n/react'

type SharedProps = {
  locale: string
  locales: string[]
}

export default function LocaleSwitcher() {
  const { locale, locales } = usePage<SharedProps>().props
  const { setLocale, t } = useTranslation()

  async function switchLocale(nextLocale: string) {
    await setLocale(nextLocale)
    router.post('/locale', { locale: nextLocale }, {
      onSuccess: () => router.reload({ only: ['navigation'] }),
    })
  }

  return (
    <select
      aria-label={t('common.language')}
      value={locale}
      onChange={(event) => void switchLocale(event.target.value)}
    >
      {locales.map((availableLocale) => (
        <option key={availableLocale} value={availableLocale}>
          {new Intl.DisplayNames([availableLocale], { type: 'language' }).of(availableLocale)}
        </option>
      ))}
    </select>
  )
}
