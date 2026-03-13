import { reactive, toRaw } from 'vue'

import type { SelectOption } from '@/types/api'

/**
 * Options 类型说明：
 * {
 *   dictKey: {
 *     api: PromiseFun,
 *     params?: Record<string, any>,
 *     transformData?(data: any): any
 *   },
 *   ...
 * }
 */
interface DictOptionItem<R = any> {
    api: (...args: any[]) => Promise<R>
    params?: Record<string, any>
    transformData?(data: R): SelectOption[]
}

interface Options {
    [propName: string]: DictOptionItem
}

/**
 * 批量请求字典数据并放到 reactive 对象中
 * @param options 键对应接口与参数及可选的 transformData 方法
 */
export function useDictOptions<T = Record<string, SelectOption[]>>(options: Options) {
    const optionsData: any = reactive({})
    const optionsKey = Object.keys(options)

    // 为每个 key 生成对应的请求函数
    const apiLists = optionsKey.map((key) => {
        const value = options[key]
        optionsData[key] = [] // 初始化
        return () => value.api(toRaw(value.params) || {})
    })

    const refresh = async () => {
        const res = await Promise.allSettled(apiLists.map((api) => api()))
        res.forEach((item, index) => {
            const key = optionsKey[index]
            if (item.status === 'fulfilled') {
                const { transformData } = options[key]
                const data = transformData ? transformData(item.value) : item.value
                optionsData[key] = data
            }
        })
    }

    // 初始化时先调用一次
    refresh()

    return {
        optionsData: optionsData as T,
        refresh
    }
}
