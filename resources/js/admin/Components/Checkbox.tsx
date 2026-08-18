import { Checkbox as BaseCheckbox } from '@base-ui/react/checkbox'
import * as stylex from '@stylexjs/stylex'
import type { ComponentProps } from 'react'

type CheckboxProps = ComponentProps<typeof BaseCheckbox.Root>

export default function Checkbox(props: CheckboxProps) {
  return (
    <BaseCheckbox.Root
      {...props}
      className={(state) => stylex.props(
        styles.root,
        state.checked && styles.checked,
        state.disabled && styles.disabled,
      ).className}
    >
      <BaseCheckbox.Indicator {...stylex.props(styles.indicator)}>
        <CheckIcon />
      </BaseCheckbox.Indicator>
    </BaseCheckbox.Root>
  )
}

function CheckIcon() {
  return (
    <svg aria-hidden="true" fill="none" height="14" viewBox="0 0 14 14" width="14">
      <path d="m2.5 7.5 3 3 6-7" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.75" />
    </svg>
  )
}

const styles = stylex.create({
  root: {
    alignItems: 'center',
    backgroundColor: '#fff',
    borderColor: 'var(--color-neutral-300)',
    borderRadius: 4,
    borderStyle: 'solid',
    borderWidth: 1,
    color: '#fff',
    cursor: 'pointer',
    display: 'flex',
    flexShrink: 0,
    height: 18,
    justifyContent: 'center',
    marginTop: 2,
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: 2,
    outlineStyle: 'solid',
    outlineWidth: 2,
    padding: 0,
    width: 18,
  },
  checked: {
    backgroundColor: 'var(--color-brand-500)',
    borderColor: 'var(--color-brand-500)',
  },
  disabled: { cursor: 'not-allowed', opacity: 0.5 },
  indicator: { alignItems: 'center', display: 'flex', justifyContent: 'center' },
})
