/**
 * 搜索请求防抖 Hook
 *
 * 使用方式：
 * const debouncedSearch = useDebounceRequest((keyword: string) => api.search(keyword))
 */
export function useDebounceRequest<T extends (...args: any[]) => Promise<any>>(
    fn: T,
    delay = 300
): (...args: Parameters<T>) => Promise<Awaited<ReturnType<T>>> {
    let timer: ReturnType<typeof setTimeout> | null = null

    return (...args: Parameters<T>): Promise<Awaited<ReturnType<T>>> => {
        if (timer) {
            clearTimeout(timer)
        }
        return new Promise((resolve, reject) => {
            timer = setTimeout(() => {
                fn(...args)
                    .then(resolve)
                    .catch(reject)
            }, delay)
        })
    }
}
