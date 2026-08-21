import { Radio as BaseRadio } from '@base-ui/react/radio'
import { RadioGroup as BaseRadioGroup } from '@base-ui/react/radio-group'
import * as stylex from '@stylexjs/stylex'
import type { ComponentProps, ReactNode } from 'react'

export type RadioGroupItem<Value extends string> = {
  disabled?: boolean
  label: ReactNode
  value: Value
}

type RadioGroupProps<Value extends string> = Omit<ComponentProps<typeof BaseRadioGroup>, 'children' | 'onValueChange' | 'value'> & {
  items: readonly RadioGroupItem<Value>[]
  onValueChange?: (value: Value) => void
  value?: Value
}

export default function RadioGroup<Value extends string>({ items, onValueChange, value, ...props }: RadioGroupProps<Value>) {
  return (
    <BaseRadioGroup
      onValueChange={(nextValue) => onValueChange?.(nextValue as Value)}
      value={value}
      {...props}
      {...stylex.props(styles.group)}
    >
      {items.map((item) => (
        <label key={item.value} {...stylex.props(styles.item, item.disabled && styles.itemDisabled)}>
          <BaseRadio.Root
            className={(state) => stylex.props(
              styles.radio,
              state.checked && styles.radioChecked,
              state.disabled && styles.radioDisabled,
            ).className}
            disabled={item.disabled}
            value={item.value}
          >
            <BaseRadio.Indicator {...stylex.props(styles.indicator)} />
          </BaseRadio.Root>
          <span>{item.label}</span>
        </label>
      ))}
    </BaseRadioGroup>
  )
}

const styles = stylex.create({
  group: { display: 'grid', gap: 2 },
  item: {
    alignItems: 'flex-start',
    borderRadius: 4,
    color: 'var(--color-neutral-800)',
    cursor: 'pointer',
    display: 'flex',
    fontSize: 14,
    fontWeight: 500,
    gap: 10,
    minHeight: 38,
    overflowWrap: 'anywhere',
    paddingBlock: 8,
    paddingInline: 8,
  },
  itemDisabled: { cursor: 'not-allowed', opacity: 0.5 },
  radio: {
    alignItems: 'center',
    backgroundColor: '#fff',
    borderColor: 'var(--color-neutral-300)',
    borderRadius: '50%',
    borderStyle: 'solid',
    borderWidth: 1,
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
  radioChecked: {
    backgroundColor: 'var(--color-brand-500)',
    borderColor: 'var(--color-brand-600)',
  },
  radioDisabled: { cursor: 'not-allowed' },
  indicator: {
    backgroundColor: '#fff',
    borderRadius: '50%',
    height: 10,
    width: 10,
  },
})
