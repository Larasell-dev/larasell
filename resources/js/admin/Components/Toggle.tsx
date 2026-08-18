import { Switch } from '@base-ui/react/switch'
import * as stylex from '@stylexjs/stylex'
import type { ComponentProps } from 'react'

type ToggleProps = ComponentProps<typeof Switch.Root>

export default function Toggle(props: ToggleProps) {
  return (
    <Switch.Root
      {...props}
      className={(state) => stylex.props(styles.root, state.checked && styles.checked).className}
    >
      <Switch.Thumb className={(state) => stylex.props(styles.thumb, state.checked && styles.thumbChecked).className} />
    </Switch.Root>
  )
}

const styles = stylex.create({
  root: {
    backgroundColor: 'var(--color-neutral-300)',
    borderRadius: 999,
    cursor: 'pointer',
    flexShrink: 0,
    height: 22,
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: 2,
    outlineStyle: 'solid',
    outlineWidth: 2,
    padding: 2,
    transitionDuration: '150ms',
    transitionProperty: 'background-color',
    width: 38,
  },
  checked: { backgroundColor: 'var(--color-brand-500)' },
  thumb: {
    backgroundColor: '#fff',
    borderRadius: '50%',
    boxShadow: '0 1px 2px rgba(20, 15, 18, 0.24)',
    display: 'block',
    height: 18,
    transform: 'translateX(0)',
    transitionDuration: '150ms',
    transitionProperty: 'transform',
    width: 18,
  },
  thumbChecked: { transform: 'translateX(16px)' },
})
