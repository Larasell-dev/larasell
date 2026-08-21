import * as stylex from '@stylexjs/stylex'
import type { StyleXStyles } from '@stylexjs/stylex'
import type { CSSProperties, ComponentProps } from 'react'

type InsetProps = Omit<ComponentProps<'div'>, 'style'> & {
  bottom?: CSSProperties['marginBottom']
  left?: CSSProperties['marginLeft']
  right?: CSSProperties['marginRight']
  style?: StyleXStyles
  top?: CSSProperties['marginTop']
}

type InsetVariables = CSSProperties & {
  '--inset-bottom': CSSProperties['marginBottom']
  '--inset-left': CSSProperties['marginLeft']
  '--inset-right': CSSProperties['marginRight']
  '--inset-top': CSSProperties['marginTop']
}

export default function Inset({ bottom = 0, left = 0, right = 0, style, top = 0, ...props }: InsetProps) {
  const variables: InsetVariables = {
    '--inset-bottom': bottom,
    '--inset-left': left,
    '--inset-right': right,
    '--inset-top': top,
  }

  return <div {...props} style={variables} {...stylex.props(styles.inset, style)} />
}

const styles = stylex.create({
  inset: {
    marginBottom: 'calc(-1 * var(--inset-bottom))',
    marginLeft: 'calc(-1 * var(--inset-left))',
    marginRight: 'calc(-1 * var(--inset-right))',
    marginTop: 'calc(-1 * var(--inset-top))',
  },
})
