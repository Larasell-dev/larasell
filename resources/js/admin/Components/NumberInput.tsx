import { NumberField } from '@base-ui/react/number-field'
import * as stylex from '@stylexjs/stylex'
import type { ComponentProps } from 'react'

type NumberInputProps = ComponentProps<typeof NumberField.Root> & {
  placeholder?: string
}

export default function NumberInput({ placeholder, ...props }: NumberInputProps) {
  return (
    <NumberField.Root {...props} {...stylex.props(styles.root)}>
      <NumberField.Input placeholder={placeholder} {...stylex.props(styles.input)} />
    </NumberField.Root>
  )
}

const styles = stylex.create({
  root: { width: '100%' },
  input: {
    backgroundClip: 'padding-box',
    backgroundColor: '#fff',
    borderColor: 'rgba(20, 15, 18, 0.18)',
    borderRadius: 6,
    borderStyle: 'solid',
    borderWidth: 1,
    boxShadow: '0 1px 2px oklch(14.5% 0.008 326 / 0.08)',
    color: 'var(--color-neutral-900)',
    fontFamily: 'inherit',
    fontSize: 15,
    fontWeight: 500,
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: 2,
    outlineStyle: 'solid',
    outlineWidth: 2,
    paddingBlock: 8,
    paddingInline: 12,
    width: '100%',
  },
})
