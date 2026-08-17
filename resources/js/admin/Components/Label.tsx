import { Field } from '@base-ui/react/field'
import * as stylex from '@stylexjs/stylex'
import type { ComponentProps } from 'react'

type LabelProps = ComponentProps<typeof Field.Label>

export default function Label(props: LabelProps) {
  return <Field.Label {...props} {...stylex.props(styles.label)} />
}

const styles = stylex.create({
  label: {
    fontSize: 14,
    fontWeight: 500,
  },
})
