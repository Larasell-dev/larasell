import { Form as BaseForm } from '@base-ui/react/form'
import * as stylex from '@stylexjs/stylex'
import type { ComponentProps } from 'react'

type FormProps = ComponentProps<typeof BaseForm>

export default function Form(props: FormProps) {
  return <BaseForm {...props} {...stylex.props(styles.form)} />
}

const styles = stylex.create({
  form: {
    display: 'grid',
    gap: 16,
    width: '100%',
  },
})
