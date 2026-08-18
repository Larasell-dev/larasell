import { Link } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import type { ReactNode } from 'react'
import Icon from './Icon'

type Props = {
  children: ReactNode
  href: string
}

export default function BackLink({ children, href }: Props) {
  return (
    <Link href={href} {...stylex.props(styles.link)}>
      <Icon height={16} name="arrow-left" width={16} />
      {children}
    </Link>
  )
}

const styles = stylex.create({
  link: {
    alignItems: 'center',
    borderRadius: 4,
    color: { default: 'var(--color-brand-700)', ':hover': 'var(--color-brand-900)' },
    display: 'inline-flex',
    fontSize: 13,
    fontWeight: 600,
    gap: 5,
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: 3,
    outlineStyle: 'solid',
    outlineWidth: 2,
    textDecoration: 'none',
  },
})
