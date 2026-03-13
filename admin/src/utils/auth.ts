import { TOKEN_KEY } from '@/constants/cache'
import { resetRouter } from '@/router'
import useTabsStore from '@/store/modules/multipleTabs.store'
import useUserStore from '@/store/modules/user.store'

import cache from './cache'

export function getToken() {
    return cache.get(TOKEN_KEY)
}

export function clearAuthInfo() {
    const userStore = useUserStore()
    const tabsStore = useTabsStore()
    userStore.resetState()
    tabsStore.resetState()
    cache.remove(TOKEN_KEY)
    resetRouter()
}
