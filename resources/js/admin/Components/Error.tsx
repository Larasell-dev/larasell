import { Field } from '@base-ui/react/field'
import * as stylex from '@stylexjs/stylex'
import type { ComponentProps } from 'react'

type ErrorProps = ComponentProps<typeof Field.Error>

export default function Error(props: ErrorProps) {
  return <Field.Error {...props} {...stylex.props(styles.error)} />
}

const styles = stylex.create({
  error: {
    color: 'oklch(50.5% 0.213 27.518)',
    fontSize: 13,
    fontWeight: 500,
    margin: 0,
  },
})
