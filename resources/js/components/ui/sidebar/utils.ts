import { createContext } from "reka-ui"
import { ref, type ComputedRef, type Ref } from 'vue';

export type SidebarContext = {
    state: ComputedRef<'expanded' | 'collapsed'>;
    open: Ref<boolean>;
    setOpen: (value: boolean) => void;
    isMobile: Ref<boolean>;
    openMobile: Ref<boolean>;
    setOpenMobile: (value: boolean) => void;
    toggleSidebar: () => void;
};

export const SIDEBAR_COOKIE_NAME = "sidebar_state"
export const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 7
export const SIDEBAR_WIDTH = "15rem"
export const SIDEBAR_WIDTH_MOBILE = "18rem"
export const SIDEBAR_WIDTH_ICON = "3.25rem"
export const SIDEBAR_KEYBOARD_SHORTCUT = "b"

export const [useSidebar, provideSidebarContext] = createContext<SidebarContext>("Sidebar")

export const SIDEBAR_RESIZE_SETTLE_MS = 260

export const sidebarResizing = ref(false)
