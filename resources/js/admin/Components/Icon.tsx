import ArrowLeftIcon from '~icons/tabler/arrow-left'
import AdjustmentsHorizontalIcon from '~icons/tabler/adjustments-horizontal'
import AdjustmentsHorizontalFilledIcon from '~icons/tabler/adjustments-horizontal-filled'
import BrandXIcon from '~icons/tabler/brand-x'
import BookIcon from '~icons/tabler/book'
import ChevronLeftIcon from '~icons/tabler/chevron-left'
import ChevronRightIcon from '~icons/tabler/chevron-right'
import LayoutDashboardIcon from '~icons/tabler/layout-dashboard'
import LayoutDashboardFilledIcon from '~icons/tabler/layout-dashboard-filled'
import LogoutIcon from '~icons/tabler/logout'
import MailIcon from '~icons/tabler/mail'
import PhotoIcon from '~icons/tabler/photo'
import PhotoFilledIcon from '~icons/tabler/photo-filled'
import MenuIcon from '~icons/tabler/menu-2'
import DotsIcon from '~icons/tabler/dots'
import PlusIcon from '~icons/tabler/plus'
import ReceiptIcon from '~icons/tabler/receipt'
import ReceiptFilledIcon from '~icons/tabler/receipt-filled'
import ShoppingCartIcon from '~icons/tabler/shopping-cart'
import ShoppingCartFilledIcon from '~icons/tabler/shopping-cart-filled'
import TrashIcon from '~icons/tabler/trash'
import XIcon from '~icons/tabler/x'
import SettingsIcon from '~icons/tabler/settings'
import UsersIcon from '~icons/tabler/users'
import WorldIcon from '~icons/tabler/world'
import type { ComponentProps } from 'react'

const icons = {
  'product-attributes': AdjustmentsHorizontalIcon,
  'product-attributes-filled': AdjustmentsHorizontalFilledIcon,
  'arrow-left': ArrowLeftIcon,
  'brand-x': BrandXIcon,
  book: BookIcon,
  'chevron-left': ChevronLeftIcon,
  'chevron-right': ChevronRightIcon,
  dashboard: LayoutDashboardIcon,
  'dashboard-filled': LayoutDashboardFilledIcon,
  dots: DotsIcon,
  menu: MenuIcon,
  media: PhotoIcon,
  'media-filled': PhotoFilledIcon,
  logout: LogoutIcon,
  mail: MailIcon,
  plus: PlusIcon,
  orders: ReceiptIcon,
  'orders-filled': ReceiptFilledIcon,
  products: ShoppingCartIcon,
  'products-filled': ShoppingCartFilledIcon,
  trash: TrashIcon,
  settings: SettingsIcon,
  users: UsersIcon,
  world: WorldIcon,
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
