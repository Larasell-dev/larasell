import { Link } from '@inertiajs/react'
import { Dialog } from '@base-ui/react/dialog'
import * as stylex from '@stylexjs/stylex'
import { useState, type ReactNode } from 'react'
import DropdownMenu from './DropdownMenu'
import Icon from './Icon'
import Logo from './Logo'
import LogoutDialog from './LogoutDialog'
import useBreakpoint from '../Hooks/useBreakpoint'

export type AdminLayoutProps = {
  homeUrl: string
  mediaUrl: string
  productsUrl: string
  productOptionsUrl: string
  logoutUrl: string
  user: { name: string; email: string }
}

type Props = AdminLayoutProps & {
  active: 'home' | 'media' | 'product-options' | 'products'
  children?: ReactNode
}

export default function AdminLayout({ active, children, homeUrl, logoutUrl, mediaUrl, productOptionsUrl, productsUrl, user }: Props) {
  const [logoutOpen, setLogoutOpen] = useState(false)
  const [menuOpen, setMenuOpen] = useState(false)
  useBreakpoint({ onBreakpointExceeded: { lg: () => setMenuOpen(false) } })

  const openLogout = () => {
    setMenuOpen(false)
    setLogoutOpen(true)
  }

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
            <Icon name={active === 'home' ? 'dashboard-filled' : 'dashboard'} width={18} height={18} />
            Dashboard
          </Link>
          <Link
            aria-current={active === 'products' ? 'page' : undefined}
            href={productsUrl}
            {...stylex.props(styles.navLink, active === 'products' && styles.navLinkActive)}
          >
            <Icon name={active === 'products' ? 'products-filled' : 'products'} width={18} height={18} />
            Products
          </Link>
          <Link
            aria-current={active === 'media' ? 'page' : undefined}
            href={mediaUrl}
            {...stylex.props(styles.navLink, active === 'media' && styles.navLinkActive)}
          >
            <Icon name={active === 'media' ? 'media-filled' : 'media'} width={18} height={18} />
            Media
          </Link>
          <Link
            aria-current={active === 'product-options' ? 'page' : undefined}
            href={productOptionsUrl}
            {...stylex.props(styles.navLink, active === 'product-options' && styles.navLinkActive)}
          >
            <Icon name={active === 'product-options' ? 'product-options-filled' : 'product-options'} width={18} height={18} />
            Product options
          </Link>
        </nav>

        <div {...stylex.props(styles.account)}>
          <DropdownMenu
            items={[{ label: 'Log out', onClick: openLogout }]}
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

      <Dialog.Root open={menuOpen} onOpenChange={setMenuOpen}>
        <header {...stylex.props(styles.mobileHeader)}>
          <Link aria-label="Larasell admin home" href={homeUrl} {...stylex.props(styles.mobileLogoLink)}>
            <Logo />
          </Link>
          <Dialog.Trigger
            aria-label={menuOpen ? 'Close navigation' : 'Open navigation'}
            {...stylex.props(styles.iconButton)}
          >
            <Icon name={menuOpen ? 'x' : 'menu'} width={22} height={22} />
          </Dialog.Trigger>
        </header>

        {menuOpen && (
          <Dialog.Portal>
            <Dialog.Backdrop {...stylex.props(styles.backdrop)} />
            <Dialog.Viewport {...stylex.props(styles.menuViewport)}>
              <Dialog.Popup {...stylex.props(styles.menuDialog)}>
                <Dialog.Title {...stylex.props(styles.visuallyHidden)}>Navigation</Dialog.Title>

                <nav aria-label="Admin navigation" {...stylex.props(styles.navigation)}>
                  <Link
                    aria-current={active === 'home' ? 'page' : undefined}
                    href={homeUrl}
                    onClick={() => setMenuOpen(false)}
                    {...stylex.props(styles.navLink, active === 'home' && styles.navLinkActive)}
                  >
                    <Icon name={active === 'home' ? 'dashboard-filled' : 'dashboard'} width={18} height={18} />
                    Dashboard
                  </Link>
                  <Link
                    aria-current={active === 'products' ? 'page' : undefined}
                    href={productsUrl}
                    onClick={() => setMenuOpen(false)}
                    {...stylex.props(styles.navLink, active === 'products' && styles.navLinkActive)}
                  >
                    <Icon name={active === 'products' ? 'products-filled' : 'products'} width={18} height={18} />
                    Products
                  </Link>
                  <Link
                    aria-current={active === 'media' ? 'page' : undefined}
                    href={mediaUrl}
                    onClick={() => setMenuOpen(false)}
                    {...stylex.props(styles.navLink, active === 'media' && styles.navLinkActive)}
                  >
                    <Icon name={active === 'media' ? 'media-filled' : 'media'} width={18} height={18} />
                    Media
                  </Link>
                  <Link
                    aria-current={active === 'product-options' ? 'page' : undefined}
                    href={productOptionsUrl}
                    onClick={() => setMenuOpen(false)}
                    {...stylex.props(styles.navLink, active === 'product-options' && styles.navLinkActive)}
                  >
                    <Icon name={active === 'product-options' ? 'product-options-filled' : 'product-options'} width={18} height={18} />
                    Product options
                  </Link>
                </nav>

                <div {...stylex.props(styles.account)}>
                  <DropdownMenu
                    items={[{ label: 'Log out', onClick: openLogout }]}
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
              </Dialog.Popup>
            </Dialog.Viewport>
          </Dialog.Portal>
        )}
      </Dialog.Root>

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
    flexDirection: 'row',
    fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
    minHeight: '100vh',
  },
  sidebar: {
    backgroundColor: '#fff',
    borderRightColor: 'var(--color-neutral-200)',
    borderRightStyle: 'solid',
    borderRightWidth: 1,
    display: { default: 'flex', '@media (max-width: 1024px)': 'none' },
    flexDirection: 'column',
    minHeight: '100vh',
    padding: 0,
    position: 'fixed',
    width: 256,
  },
  mobileHeader: {
    alignItems: 'center',
    backgroundColor: '#fff',
    borderBottomColor: 'var(--color-neutral-200)',
    borderBottomStyle: 'solid',
    borderBottomWidth: 1,
    display: { default: 'none', '@media (max-width: 1024px)': 'flex' },
    height: 'var(--admin-header-height)',
    justifyContent: 'space-between',
    paddingInline: 16,
    position: 'fixed',
    top: 0,
    width: '100%',
    zIndex: 50,
  },
  mobileLogoLink: {
    alignItems: 'center',
    display: 'flex',
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: 4,
    outlineStyle: 'solid',
    outlineWidth: 2,
  },
  navigation: { display: 'grid', gap: 4, padding: 16 },
  navLink: {
    alignItems: 'center',
    backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-100)' },
    borderRadius: 6,
    color: 'var(--color-neutral-700)',
    display: 'flex',
    fontSize: 14,
    fontWeight: 600,
    gap: 10,
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
  iconButton: {
    alignItems: 'center',
    backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-100)' },
    borderColor: 'transparent',
    borderRadius: 6,
    borderStyle: 'solid',
    borderWidth: 1,
    color: 'var(--color-neutral-800)',
    cursor: 'pointer',
    display: 'flex',
    height: 40,
    justifyContent: 'center',
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: 2,
    outlineStyle: 'solid',
    outlineWidth: 2,
    padding: 0,
    width: 40,
  },
  backdrop: {
    backdropFilter: 'blur(2px)',
    backgroundImage: 'linear-gradient(rgb(0 0 0 / 10%), rgb(0 0 0 / 10%))',
    bottom: 0,
    left: 0,
    position: 'fixed',
    right: 0,
    top: 'var(--admin-header-height)',
    zIndex: 40,
  },
  menuViewport: {
    alignItems: 'flex-start',
    bottom: 0,
    display: 'flex',
    justifyContent: 'flex-end',
    left: 0,
    position: 'fixed',
    right: 0,
    top: 'var(--admin-header-height)',
    zIndex: 41,
  },
  menuDialog: {
    backgroundClip: 'padding-box',
    backgroundColor: '#fff',
    borderColor: 'color-mix(in srgb, var(--color-neutral-300) 60%, transparent)',
    borderStyle: 'solid',
    borderWidth: 0,
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    outline: 'none',
    overflowY: 'auto',
    width: '100%',
  },
  visuallyHidden: {
    clip: 'rect(0 0 0 0)',
    clipPath: 'inset(50%)',
    height: 1,
    overflow: 'hidden',
    position: 'absolute',
    whiteSpace: 'nowrap',
    width: 1,
  },
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
  main: {
    marginLeft: { default: 256, '@media (max-width: 1024px)': 0 },
    minWidth: 0,
    paddingTop: { default: 0, '@media (max-width: 1024px)': 'var(--admin-header-height)' },
    width: '100%',
  },
})
