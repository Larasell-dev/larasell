import * as stylex from '@stylexjs/stylex'
import type { ComponentProps } from 'react'

type FormContainerProps = ComponentProps<'div'>

export default function FormContainer(props: FormContainerProps) {
  return <div {...props} {...stylex.props(styles.container)} />
}

const styles = stylex.create({
  container: {
    maxWidth: 380,
    width: '100%',
  },
})
