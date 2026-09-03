import { Head } from '@inertiajs/react'
import { useTranslation } from '@larasell-dev/inertia-i18n/react'

function Home() {
  const { t } = useTranslation()

  return (
    <main>
      <Head title={t('home.title')} />
      <h1>{t('home.heading')}</h1>
    </main>
  )
}

Home.translation = ['home']

export default Home
