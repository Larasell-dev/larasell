import * as stylex from '@stylexjs/stylex'
import logoUrl from '../../../images/admin/logo-light.svg'

export default function Logo() {
  return (
    <img
      alt="Larasell"
      height="200"
      src={logoUrl}
      width="781"
      {...stylex.props(styles.logo)}
    />
  )
}

const styles = stylex.create({
  logo: {
    borderRadius: 4,
    height: 'auto',
    width: 104,
  },
})
