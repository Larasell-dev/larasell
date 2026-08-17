import { Button as BaseButton } from '@base-ui/react/button'
import * as stylex from '@stylexjs/stylex'
import type { ComponentProps } from 'react'

type ButtonProps = ComponentProps<typeof BaseButton>

export default function Button({ disabled, ...props }: ButtonProps) {
  return (
    <BaseButton
      disabled={disabled}
      {...props}
      {...stylex.props(styles.button, disabled && styles.disabled)}
    />
  )
}

const styles = stylex.create({
  button: {
    appearance: 'none',
    backgroundColor: 'var(--color-brand-500)',
    borderStyle: 'none',
    borderWidth: 0,
    borderRadius: 6,
    boxSizing: 'border-box',
    color: '#fff',
    cursor: 'pointer',
    fontFamily: 'inherit',
    fontSize: 15,
    fontWeight: 600,
    letterSpacing: 0,
    lineHeight: 'inherit',
    marginBottom: 0,
    marginLeft: 0,
    marginRight: 0,
    marginTop: 8,
    outlineColor: {
      default: 'transparent',
      ':focus-visible': 'var(--color-brand-400)',
    },
    outlineOffset: 2,
    outlineStyle: 'solid',
    outlineWidth: 2,
    paddingBottom: 9,
    paddingLeft: 14,
    paddingRight: 14,
    paddingTop: 9,
    textAlign: 'center',
    textTransform: 'none',
  },
  disabled: {
    cursor: 'wait',
    opacity: 0.65,
  },
})
