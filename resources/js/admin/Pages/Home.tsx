import { router } from '@inertiajs/react'
import type { CSSProperties } from 'react'

type HomeProps = {
  logoutUrl: string
}

export default function Home({ logoutUrl }: HomeProps) {
  function logout() {
    router.post(logoutUrl)
  }

  return (
    <main style={styles.page}>
      <section style={styles.section}>
        <h1 style={styles.heading}>Larasell Admin</h1>

        <button
          onClick={logout}
          style={styles.button}
          type="button"
        >
          Sign out
        </button>
      </section>
    </main>
  )
}

const styles = {
  page: {
    background: '#f6f5f2',
    color: '#202020',
    display: 'grid',
    fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
    minHeight: '100vh',
    padding: '0 16px',
    placeItems: 'center',
  },
  section: {
    alignItems: 'center',
    display: 'flex',
    gap: 24,
    justifyContent: 'space-between',
    maxWidth: 760,
    width: '100%',
  },
  heading: {
    fontSize: 28,
    fontWeight: 650,
    margin: 0,
  },
  button: {
    background: '#202020',
    border: 0,
    borderRadius: 6,
    color: '#fff',
    cursor: 'pointer',
    font: 'inherit',
    fontWeight: 600,
    padding: '10px 14px',
  },
} satisfies Record<string, CSSProperties>
