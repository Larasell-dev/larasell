import { Link } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import { useState, type ReactNode } from 'react'
import DropdownMenu from './DropdownMenu'
import Logo from './Logo'
import LogoutDialog from './LogoutDialog'

export type AdminLayoutProps = {
  homeUrl: string
  productsUrl: string
  logoutUrl: string
  user: { name: string; email: string }
}

type Props = AdminLayoutProps & {
  active: 'home' | 'products'
  children?: ReactNode
}

export default function AdminLayout({ active, children, homeUrl, logoutUrl, productsUrl, user }: Props) {
  const [logoutOpen, setLogoutOpen] = useState(false)

  return (
    <div {...stylex.props(styles.page)}>
      <aside {...stylex.props(styles.sidebar)}>
        <Link aria-label="Larasell admin home" href={homeUrl} {...stylex.props(styles.logoLink)}>
          <Logo />
        </Link>

        <nav aria-label="Admin navigation" {...stylex.props(styles.navigation)}>
          <Link
            aria-current={active === 'home' ? 'page' : undefined}
            href={homeUrl}
            {...stylex.props(styles.navLink, active === 'home' && styles.navLinkActive)}
          >
            Dashboard
          </Link>
          <Link
            aria-current={active === 'products' ? 'page' : undefined}
            href={productsUrl}
            {...stylex.props(styles.navLink, active === 'products' && styles.navLinkActive)}
          >
            Products
          </Link>
        </nav>

        <div {...stylex.props(styles.account)}>
          <DropdownMenu
            items={[{ label: 'Log out', onClick: () => setLogoutOpen(true) }]}
            trigger={
              <button type="button" {...stylex.props(styles.userTrigger)}>
                <span aria-hidden="true" {...stylex.props(styles.avatar)}>{initials(user.name)}</span>
                <span {...stylex.props(styles.userDetails)}>
                  <strong {...stylex.props(styles.userName)}>{user.name}</strong>
                  <span {...stylex.props(styles.userEmail)}>{user.email}</span>
                </span>
              </button>
            }
          />
        </div>
      </aside>

      <main {...stylex.props(styles.main)}>{children}</main>
      <LogoutDialog logoutUrl={logoutUrl} onOpenChange={setLogoutOpen} open={logoutOpen} />
    </div>
  )
}

function initials(name: string) {
  return name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase()
}

const styles = stylex.create({
  page: {
    background: 'var(--color-neutral-50)',
    color: 'var(--color-neutral-950)',
    display: 'flex',
    flexDirection: { default: 'row', '@media (max-width: 640px)': 'column' },
    fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
    minHeight: '100vh',
  },
  sidebar: {
    backgroundColor: '#fff',
    borderRightColor: 'var(--color-neutral-200)',
    borderRightStyle: 'solid',
    borderRightWidth: { default: 1, '@media (max-width: 640px)': 0 },
    borderBottomColor: 'var(--color-neutral-200)',
    borderBottomStyle: 'solid',
    borderBottomWidth: { default: 0, '@media (max-width: 640px)': 1 },
    display: 'flex',
    flexDirection: 'column',
    minHeight: { default: '100vh', '@media (max-width: 640px)': 'auto' },
    padding: 0,
    position: { default: 'fixed', '@media (max-width: 640px)': 'relative' },
    width: { default: 256, '@media (max-width: 640px)': '100%' },
  },
  navigation: { display: 'grid', gap: 4, padding: 16 },
  navLink: {
    backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-100)' },
    borderRadius: 6,
    color: 'var(--color-neutral-700)',
    fontSize: 14,
    fontWeight: 600,
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: 2,
    outlineStyle: 'solid',
    outlineWidth: 2,
    paddingBlock: 9,
    paddingInline: 10,
    textDecoration: 'none',
  },
  navLinkActive: { backgroundColor: 'var(--color-brand-50)', color: 'var(--color-brand-900)' },
  account: { marginTop: 'auto', padding: 16 },
  logoLink: {
    alignItems: 'center',
    boxShadow: 'inset 0 -1px 0 var(--color-neutral-200)',
    display: 'flex',
    flexShrink: 0,
    height: 'var(--admin-header-height)',
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: -3,
    outlineStyle: 'solid',
    outlineWidth: 2,
    paddingInline: 16,
    width: '100%',
  },
  userTrigger: {
    alignItems: 'center',
    backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-100)' },
    borderRadius: 6,
    display: 'flex',
    gap: 10,
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: 2,
    outlineStyle: 'solid',
    outlineWidth: 2,
    padding: 8,
    textAlign: 'left',
    width: '100%',
  },
  avatar: {
    alignItems: 'center', backgroundColor: 'var(--color-brand-100)', borderRadius: 6,
    color: 'var(--color-brand-900)', display: 'flex', flexShrink: 0, fontSize: 12,
    fontWeight: 700, height: 36, justifyContent: 'center', width: 36,
  },
  userDetails: { display: 'grid', flex: 1, minWidth: 0 },
  userName: { fontSize: 14, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' },
  userEmail: { color: 'var(--color-neutral-500)', fontSize: 12, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' },
  main: { marginLeft: { default: 256, '@media (max-width: 640px)': 0 }, minWidth: 0, width: '100%' },
})
