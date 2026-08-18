import ArrowLeftIcon from '~icons/tabler/arrow-left'
import type { ComponentProps } from 'react'

const icons = {
  'arrow-left': ArrowLeftIcon,
}

export type IconName = keyof typeof icons

type IconProps = ComponentProps<typeof ArrowLeftIcon> & {
  name: IconName
}

export default function Icon({ name, ...props }: IconProps) {
  const IconComponent = icons[name]

  return <IconComponent aria-hidden="true" focusable="false" {...props} />
}
