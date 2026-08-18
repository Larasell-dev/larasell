import { Button as BaseButton } from '@base-ui/react/button'
import * as stylex from '@stylexjs/stylex'
import type { ComponentProps } from 'react'

type ButtonProps = ComponentProps<typeof BaseButton> & {
  variant?: 'danger' | 'primary' | 'secondary'
}

export default function Button({ disabled, variant = 'primary', ...props }: ButtonProps) {
  return (
    <BaseButton
      disabled={disabled}
      {...props}
      {...stylex.props(
        styles.button,
        variant === 'primary' ? styles.primary : variant === 'danger' ? styles.danger : styles.secondary,
        disabled && styles.disabled,
      )}
    />
  )
}

const styles = stylex.create({
  button: {
    alignItems: 'center',
    appearance: 'none',
    borderStyle: 'solid',
    borderWidth: 1,
    borderRadius: 6,
    boxSizing: 'border-box',
    cursor: 'pointer',
    display: 'inline-flex',
    fontFamily: 'inherit',
    fontSize: 15,
    fontWeight: 600,
    gap: 7,
    justifyContent: 'center',
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
    textDecoration: 'none',
    textTransform: 'none',
  },
  primary: {
    backgroundColor: { default: 'var(--color-brand-500)', ':hover': 'var(--color-brand-600)' },
    borderColor: { default: 'var(--color-brand-500)', ':hover': 'var(--color-brand-600)' },
    color: '#fff',
  },
  secondary: {
    backgroundColor: { default: '#fff', ':hover': 'var(--color-neutral-100)' },
    borderColor: 'var(--color-neutral-300)',
    color: 'var(--color-neutral-900)',
  },
  danger: {
    backgroundColor: { default: '#dc2626', ':hover': '#b91c1c' },
    borderColor: { default: '#dc2626', ':hover': '#b91c1c' },
    color: '#fff',
  },
  disabled: {
    cursor: 'wait',
    opacity: 0.65,
  },
})
