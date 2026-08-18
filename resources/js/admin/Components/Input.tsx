import { Input as BaseInput } from '@base-ui/react/input'
import * as stylex from '@stylexjs/stylex'
import type { ComponentProps } from 'react'

type InputProps = ComponentProps<typeof BaseInput>

export default function Input(props: InputProps) {
  return <BaseInput {...props} {...stylex.props(styles.input)} />
}

const styles = stylex.create({
  input: {
    backgroundClip: 'padding-box',
    backgroundColor: '#fff',
    borderColor: 'rgba(20, 15, 18, 0.18)',
    borderStyle: 'solid',
    borderWidth: 1,
    borderRadius: 6,
    boxShadow: '0 1px 2px oklch(14.5% 0.008 326 / 0.08)',
    color: 'var(--color-neutral-900)',
    fontFamily: 'inherit',
    fontSize: 15,
    fontWeight: 500,
    outlineColor: {
      default: 'transparent',
      ':focus-visible': 'var(--color-brand-400)',
    },
    outlineOffset: 2,
    outlineStyle: 'solid',
    outlineWidth: 2,
    paddingBottom: 8,
    paddingLeft: 12,
    paddingRight: 12,
    paddingTop: 8,
  },
})
