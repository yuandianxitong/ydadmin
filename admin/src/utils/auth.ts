import { TOKEN_KEY } from '@/constants/cache'
import { resetRouter } from '@/router'
import useTabsStore from '@/store/modules/multipleTabs.store'
import useRealtimeStore from '@/store/modules/realtime.store'
import useUserStore from '@/store/modules/user.store'

import cache from './cache'

export function getToken() {
    return cache.get(TOKEN_KEY)
}

export function clearAuthInfo() {
    const userStore = useUserStore()
    const tabsStore = useTabsStore()
    // 登出、401、刷新失败、强制下线都经过这里：先断开实时通道，避免已失效的连接继续重连
    useRealtimeStore().disconnect()
    userStore.resetState()
    tabsStore.resetState()
    cache.remove(TOKEN_KEY)
    resetRouter()
}
