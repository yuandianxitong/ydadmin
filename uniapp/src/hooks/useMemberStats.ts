import { inject, provide, ref, type InjectionKey, type Ref } from 'vue'

import { userApi } from '@/api/user'
import { useUserStore } from '@/store/user.store'

export type MemberStats = Record<string, number | string>

export const MEMBER_STATS_KEY: InjectionKey<Ref<MemberStats>> = Symbol('member-stats')

type DiyComponentLike = { props?: Record<string, any> }

/** 收集装修树上用到的全部统计键：资产格 stat_key + 项目 badge_key（含插件 widget items） */
export function collectStatKeys(components?: DiyComponentLike[]): string[] {
  const keys = new Set<string>()
  for (const c of components || []) {
    const p = c.props || {}
    for (const a of (p.assets as any[]) || []) {
      if (a?.stat_key) keys.add(String(a.stat_key))
    }
    for (const it of (p.items as any[]) || []) {
      if (it?.badge_key) keys.add(String(it.badge_key))
      if (it?.stat_key) keys.add(String(it.stat_key))
    }
  }
  return [...keys]
}

/**
 * 页面级安装（member 页等 DiyRenderer 宿主 setup 里调用一次）：
 * 未登录不发请求（资产 ** / 角标不显示由消费组件兜底）；拉取失败静默（角标退化不显示）。
 */
export function provideMemberStats() {
  const stats = ref<MemberStats>({})
  provide(MEMBER_STATS_KEY, stats)
  const userStore = useUserStore()

  async function refreshKeys(keys: string[]) {
    if (!userStore.isLoggedIn || !keys.length) {
      stats.value = {}
      return
    }
    try {
      stats.value = (await userApi.getMemberStats(keys)) || {}
    } catch {
      // 静默：保留上次值，角标/数字退化
    }
  }

  function refresh(components?: DiyComponentLike[]) {
    return refreshKeys(collectStatKeys(components))
  }

  return { stats, refresh, refreshKeys }
}

/** 组件侧消费：不在 provider 下（如自定义页未装）时得到空 Map，各组件自行兜底 */
export function useMemberStats(): Ref<MemberStats> {
  return inject(MEMBER_STATS_KEY, ref({}))
}
