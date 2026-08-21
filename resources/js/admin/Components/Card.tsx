import * as stylex from '@stylexjs/stylex'
import type { ComponentProps, JSX } from 'react'

export const cardBodySpacing = {
  paddingBlock: 'var(--card-body-padding-block, 20px)',
  paddingInline: 'var(--card-body-padding-inline, 20px)',
} as const

type ElementProps<T extends keyof JSX.IntrinsicElements> = ComponentProps<T>

function CardRoot(props: ElementProps<'section'>) {
  return <section {...props} {...stylex.props(styles.card)} />
}

export function CardHeader(props: ElementProps<'header'>) {
  return <header {...props} {...stylex.props(styles.header)} />
}

export function CardTitle(props: ElementProps<'h2'>) {
  return <h2 {...props} {...stylex.props(styles.title)} />
}

export function CardDescription(props: ElementProps<'p'>) {
  return <p {...props} {...stylex.props(styles.description)} />
}

export function CardBody(props: ElementProps<'div'>) {
  return <div {...props} {...stylex.props(styles.body)} />
}

const Card = Object.assign(CardRoot, {
  Header: CardHeader,
  Title: CardTitle,
  Description: CardDescription,
  Body: CardBody,
})

export default Card

const styles = stylex.create({
  card: {
    display: 'grid',
    gap: 10,
  },
  header: {
    display: 'grid',
    gap: 4,
  },
  title: { color: 'var(--color-neutral-950)', fontSize: 16, fontWeight: 650, lineHeight: 1.4 },
  description: { color: 'var(--color-neutral-500)', fontSize: 14, lineHeight: 1.5 },
  body: {
    backgroundClip: 'padding-box',
    backgroundColor: '#fff',
    borderColor: 'rgba(20, 15, 18, 0.12)',
    borderRadius: 8,
    borderStyle: 'solid',
    borderWidth: 1,
    boxShadow: '0 1px 2px oklch(14.5% 0.008 326 / 0.05)',
    display: 'grid',
    gap: 16,
    paddingBlock: cardBodySpacing.paddingBlock,
    paddingInline: cardBodySpacing.paddingInline,
  },
})
