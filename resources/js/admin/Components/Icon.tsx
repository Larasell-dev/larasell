import ArrowLeftIcon from '~icons/tabler/arrow-left'
import AdjustmentsHorizontalIcon from '~icons/tabler/adjustments-horizontal'
import AdjustmentsHorizontalFilledIcon from '~icons/tabler/adjustments-horizontal-filled'
import ChevronLeftIcon from '~icons/tabler/chevron-left'
import ChevronRightIcon from '~icons/tabler/chevron-right'
import LayoutDashboardIcon from '~icons/tabler/layout-dashboard'
import LayoutDashboardFilledIcon from '~icons/tabler/layout-dashboard-filled'
import LogoutIcon from '~icons/tabler/logout'
import PhotoIcon from '~icons/tabler/photo'
import PhotoFilledIcon from '~icons/tabler/photo-filled'
import MenuIcon from '~icons/tabler/menu-2'
import DotsIcon from '~icons/tabler/dots'
import PlusIcon from '~icons/tabler/plus'
import ShoppingCartIcon from '~icons/tabler/shopping-cart'
import ShoppingCartFilledIcon from '~icons/tabler/shopping-cart-filled'
import TrashIcon from '~icons/tabler/trash'
import XIcon from '~icons/tabler/x'
import SettingsIcon from '~icons/tabler/settings'
import UsersIcon from '~icons/tabler/users'
import type { ComponentProps } from 'react'

const icons = {
  'product-options': AdjustmentsHorizontalIcon,
  'product-options-filled': AdjustmentsHorizontalFilledIcon,
  'arrow-left': ArrowLeftIcon,
  'chevron-left': ChevronLeftIcon,
  'chevron-right': ChevronRightIcon,
  dashboard: LayoutDashboardIcon,
  'dashboard-filled': LayoutDashboardFilledIcon,
  dots: DotsIcon,
  menu: MenuIcon,
  media: PhotoIcon,
  'media-filled': PhotoFilledIcon,
  logout: LogoutIcon,
  plus: PlusIcon,
  products: ShoppingCartIcon,
  'products-filled': ShoppingCartFilledIcon,
  trash: TrashIcon,
  settings: SettingsIcon,
  users: UsersIcon,
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
