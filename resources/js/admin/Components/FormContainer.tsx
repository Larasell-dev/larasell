import * as stylex from '@stylexjs/stylex'
import type { StyleXStyles } from '@stylexjs/stylex'
import type { ComponentProps } from 'react'

type FormContainerProps = Omit<ComponentProps<'div'>, 'style'> & {
  style?: StyleXStyles
}

export default function FormContainer({ style, ...props }: FormContainerProps) {
  return <div {...props} {...stylex.props(styles.container, style)} />
}

const styles = stylex.create({
  container: {
    marginInline: 'auto',
    maxWidth: 960,
    width: '100%',
  },
})
