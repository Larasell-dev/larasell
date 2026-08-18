import * as stylex from '@stylexjs/stylex'
import type { ReactNode } from 'react'

type Props = {
  actions?: ReactNode
  description: string
  renderIcon: () => ReactNode
  title: string
}

export default function EmptyState({ actions, description, renderIcon, title }: Props) {
  return (
    <div {...stylex.props(styles.root)}>
      <span aria-hidden="true" {...stylex.props(styles.icon)}>{renderIcon()}</span>
      <div {...stylex.props(styles.content)}>
        <h2 {...stylex.props(styles.title)}>{title}</h2>
        <p {...stylex.props(styles.description)}>{description}</p>
      </div>
      {actions && <div {...stylex.props(styles.actions)}>{actions}</div>}
    </div>
  )
}

const styles = stylex.create({
  root: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    justifyContent: 'center',
    padding: 32,
    textAlign: 'center',
  },
  icon: {
    alignItems: 'center',
    backgroundColor: 'var(--color-neutral-100)',
    borderRadius: 6,
    color: 'var(--color-neutral-600)',
    display: 'flex',
    height: 48,
    justifyContent: 'center',
    width: 48,
  },
  content: { marginTop: 16, maxWidth: 400 },
  title: { fontSize: 16, fontWeight: 700, lineHeight: 1.4, margin: 0 },
  description: { color: 'var(--color-neutral-500)', fontSize: 14, lineHeight: 1.5, marginBottom: 0, marginTop: 6 },
  actions: { alignItems: 'center', display: 'flex', gap: 8, justifyContent: 'center', marginTop: 20 },
})
