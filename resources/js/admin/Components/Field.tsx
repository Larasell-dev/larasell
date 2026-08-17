import { Field as BaseField } from '@base-ui/react/field'
import * as stylex from '@stylexjs/stylex'
import type { ComponentProps } from 'react'

type FieldProps = ComponentProps<typeof BaseField.Root>

export default function Field(props: FieldProps) {
  return <BaseField.Root {...props} {...stylex.props(styles.field)} />
}

const styles = stylex.create({
  field: {
    display: 'grid',
    gap: 6,
  },
})
