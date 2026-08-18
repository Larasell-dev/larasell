import ArrowLeftIcon from '~icons/tabler/arrow-left'
import LayoutDashboardIcon from '~icons/tabler/layout-dashboard'
import LayoutDashboardFilledIcon from '~icons/tabler/layout-dashboard-filled'
import MenuIcon from '~icons/tabler/menu-2'
import ShoppingCartIcon from '~icons/tabler/shopping-cart'
import ShoppingCartFilledIcon from '~icons/tabler/shopping-cart-filled'
import XIcon from '~icons/tabler/x'
import type { ComponentProps } from 'react'

const icons = {
  'arrow-left': ArrowLeftIcon,
  dashboard: LayoutDashboardIcon,
  'dashboard-filled': LayoutDashboardFilledIcon,
  menu: MenuIcon,
  products: ShoppingCartIcon,
  'products-filled': ShoppingCartFilledIcon,
  x: XIcon,
}

export type IconName = keyof typeof icons

type IconProps = ComponentProps<typeof ArrowLeftIcon> & {
  name: IconName
}

export default function Icon({ name, ...props }: IconProps) {
  const IconComponent = icons[name]

  return <IconComponent aria-hidden="true" focusable="false" {...props} />
}
